<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use App\Models\DoorSale;
use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\VendorApplication;
use App\Support\Money;
use Illuminate\Http\Request;

/**
 * Door check-in by private link. Volunteers use it without a login, but only
 * for one event, and resetting the link in admin cuts off the old one.
 * Built for a phone held in one hand: big buttons, one action per tap.
 */
class CheckinController extends Controller
{
    private function event(string $token): Event
    {
        abort_unless(preg_match('/^[a-z0-9]{32}$/', $token), 404);

        // Scoped to this subdomain's organizer, so another organizer's link 404s here.
        return Event::where('checkin_token', $token)->firstOrFail();
    }

    public static function stats(Event $event): array
    {
        $tickets = Ticket::where('event_id', $event->id)->where('status', 'valid');
        $door = DoorSale::where('event_id', $event->id)->get();
        $vendors = VendorApplication::where('event_id', $event->id)->where('status', 'paid');

        $sales = $door->where('kind', 'sale');
        $online = (clone $tickets)->count();
        $onlineIn = (clone $tickets)->whereNotNull('checked_in_at')->count();
        $doorQty = (int) $sales->sum('quantity');
        $kids = (int) $door->where('kind', 'kids')->sum('quantity');

        return [
            'online_issued' => $online,
            'online_in' => $onlineIn,
            'door_qty' => $doorQty,
            'door_cash' => (int) $sales->where('method', 'cash')->sum('amount_cents'),
            'door_card' => (int) $sales->where('method', 'card')->sum('amount_cents'),
            'door_other' => (int) $sales->where('method', 'other')->sum('amount_cents'),
            'kids' => $kids,
            'attendance' => $onlineIn + $doorQty + $kids,
            'vendors_paid' => (clone $vendors)->count(),
            'vendors_in' => (clone $vendors)->whereNotNull('checked_in_at')->count(),
        ];
    }

    public function show(Request $request, string $token)
    {
        $event = $this->event($token);
        $vendorSearch = trim((string) $request->query('v', ''));

        return view('checkin.show', [
            'event' => $event,
            'token' => $token,
            'stats' => self::stats($event),
            'ticketTypes' => $event->ticketTypes()->get(),
            'recentDoor' => DoorSale::where('event_id', $event->id)->latest('id')->limit(5)->get(),
            'vendors' => VendorApplication::where('event_id', $event->id)->where('status', 'paid')
                ->when($vendorSearch !== '', fn ($q) => $q->where(fn ($q) => $q->where('business_name', 'like', "%{$vendorSearch}%")
                    ->orWhere('contact_name', 'like', "%{$vendorSearch}%")->orWhere('table_numbers', 'like', "%{$vendorSearch}%")))
                ->orderByRaw('checked_in_at is not null')->orderBy('business_name')->get(),
            'vendorSearch' => $vendorSearch,
            'tab' => $request->query('tab', 'tickets'),
        ]);
    }

    /** Look up a ticket by code (or a buyer by name/email) and check it in. */
    public function scan(Request $request, string $token)
    {
        $event = $this->event($token);
        $input = trim((string) $request->input('code'));
        $code = Ticket::normalizeCode($input);
        $back = redirect()->route('checkin', ['token' => $token, 'tab' => 'tickets']);

        $ticket = strlen($code) === 8
            ? Ticket::with('ticketType', 'order')->where('event_id', $event->id)->where('code', $code)->first()
            : null;

        if (! $ticket) {
            // Fall back to searching orders by name or email.
            $orders = $input === '' ? collect() : TicketOrder::with('tickets.ticketType')
                ->where('event_id', $event->id)->where('status', 'paid')
                ->where(fn ($q) => $q->where('buyer_name', 'like', "%{$input}%")->orWhere('email', 'like', "%{$input}%"))
                ->limit(5)->get();
            if ($orders->isEmpty()) {
                return $back->with('scan', ['tone' => 'bad', 'title' => 'Not found', 'detail' => "No ticket or buyer matches “{$input}” for this event."]);
            }

            return $back->with('matches', $orders->map(fn ($o) => [
                'name' => $o->buyer_name,
                'email' => $o->email,
                'tickets' => $o->tickets->map(fn ($t) => ['code' => $t->code, 'display' => $t->displayCode(), 'type' => $t->ticketType->name, 'in' => (bool) $t->checked_in_at])->all(),
            ])->all());
        }

        if ($ticket->status !== 'valid') {
            return $back->with('scan', ['tone' => 'bad', 'title' => 'Ticket void', 'detail' => $ticket->displayCode().' was cancelled.']);
        }
        if ($ticket->checked_in_at) {
            return $back->with('scan', ['tone' => 'warn', 'title' => 'Already checked in',
                'detail' => $ticket->order->buyer_name.' · '.$ticket->ticketType->name.' · at '.$ticket->checked_in_at->format('g:i A')]);
        }
        $ticket->update(['checked_in_at' => now()]);

        return $back->with('scan', ['tone' => 'ok', 'title' => 'Welcome in',
            'detail' => $ticket->order->buyer_name.' · '.$ticket->ticketType->name.' · '.$ticket->displayCode()]);
    }

    public function vendor(Request $request, string $token, string $application)
    {
        $event = $this->event($token);
        $app = VendorApplication::where('event_id', $event->id)->where('status', 'paid')->findOrFail($application);
        $app->update(['checked_in_at' => $app->checked_in_at ? null : now()]);

        return redirect()->route('checkin', ['token' => $token, 'tab' => 'vendors', 'v' => $request->input('v')]);
    }

    /** Records a sale at the door (cash, card or other), or free kids. */
    public function door(Request $request, string $token)
    {
        $event = $this->event($token);
        $data = $request->validate([
            'kind' => ['required', 'in:sale,kids'],
            'ticket_type_id' => ['nullable', 'integer'],
            'quantity' => ['required', 'integer', 'min:1', 'max:50'],
            'method' => ['nullable', 'in:cash,card,other'],
            'amount' => ['nullable', 'string', 'max:12'],
        ]);
        $back = redirect()->route('checkin', ['token' => $token, 'tab' => 'door']);

        if ($data['kind'] === 'kids') {
            DoorSale::create(['event_id' => $event->id, 'kind' => 'kids', 'label' => 'Free kids', 'quantity' => $data['quantity'],
                'amount_cents' => 0, 'method' => 'other']);

            return $back->with('status', "Counted {$data['quantity']} free kids.");
        }

        $type = ! empty($data['ticket_type_id']) ? $event->ticketTypes()->find($data['ticket_type_id']) : null;
        $unit = $type?->price_cents ?? Money::parse($data['amount'] ?? '');
        if ($unit === null) {
            return $back->with('error', 'Enter a price for this sale.');
        }
        $total = $unit * $data['quantity'];
        DoorSale::create([
            'event_id' => $event->id,
            'kind' => 'sale',
            'ticket_type_id' => $type?->id,
            'label' => $type?->name ?? 'Admission',
            'quantity' => $data['quantity'],
            'amount_cents' => $total,
            'method' => $data['method'] ?? 'cash',
        ]);

        return $back->with('status', "Sold {$data['quantity']} × ".($type?->name ?? 'Admission').' for '.Money::format($total).' ('.($data['method'] ?? 'cash').').');
    }

    public function undoDoor(string $token)
    {
        $event = $this->event($token);
        $last = DoorSale::where('event_id', $event->id)->latest('id')->first();
        $last?->delete();

        return redirect()->route('checkin', ['token' => $token, 'tab' => 'door'])
            ->with('status', $last ? "Removed: {$last->quantity} × {$last->label}." : 'Nothing to undo.');
    }
}
