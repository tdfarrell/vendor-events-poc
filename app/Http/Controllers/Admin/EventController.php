<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Site\CheckinController;
use App\Models\Event;
use App\Models\TableType;
use App\Models\TicketType;
use App\Services\Bookings;
use App\Services\Ticketing;
use App\Support\Money;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class EventController extends Controller
{
    public function create(Request $request)
    {
        $type = array_key_exists($request->query('type'), config('event_types')) ? $request->query('type') : null;

        return view('admin.event-form', [
            'event' => new Event([
                'type' => $type ?? 'card_show',
                'categories' => $type ? implode(', ', config("event_types.$type.categories")) : '',
                'admission_note' => $type && config("event_types.$type.free_admission") ? 'Free admission' : '',
                'vendor_payment_mode' => 'review',
                'payment_window_days' => 7,
            ]),
            'chooseType' => $type === null,
        ]);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        $template = config('event_types.'.$data['type']);

        $event = DB::transaction(function () use ($data, $template) {
            $event = new Event($data);
            $event->slug = Event::uniqueSlug($data['title']);
            $event->save();
            foreach ($template['space_types'] as $i => $t) {
                $event->tableTypes()->create($t + ['sort_order' => $i]);
            }
            foreach ($template['ticket_types'] as $i => $t) {
                $event->ticketTypes()->create($t + ['sort_order' => $i]);
            }

            return $event;
        });

        return redirect()->route('admin.events.show', $event)
            ->with('status', 'Event created with the usual '.$template['spaces'].' for a '.Str::lower($template['label']).'. Adjust prices and counts below.');
    }

    public function show(Request $request, Event $event)
    {
        $statusFilter = (string) $request->query('status', 'active');
        $applications = $event->applications()->with('tableType')
            ->when($statusFilter === 'active', fn ($q) => $q->whereNotIn('status', ['incomplete', 'declined', 'cancelled', 'checkout']))
            ->when($statusFilter !== 'active' && $statusFilter !== 'all', fn ($q) => $q->where('status', $statusFilter))
            ->orderByRaw("case status when 'pending' then 0 when 'approved' then 1 when 'waitlisted' then 2 when 'paid' then 3 else 4 end")
            ->orderBy('created_at')
            ->get();

        return view('admin.event', [
            'event' => $event,
            'tab' => $request->query('tab', 'vendors'),
            'statusFilter' => $statusFilter,
            'spaces' => Bookings::availability($event),
            'applications' => $applications,
            'ticketTypes' => Ticketing::availability($event, true),
            'orders' => $event->ticketOrders()->with('items')->where('status', 'paid')->latest()->limit(100)->get(),
            'stats' => CheckinController::stats($event),
            'money' => $this->finances($event),
        ]);
    }

    public function edit(Event $event)
    {
        return view('admin.event-form', ['event' => $event, 'chooseType' => false]);
    }

    public function update(Request $request, Event $event)
    {
        $event->update($this->validated($request, $event));

        return redirect()->route('admin.events.show', $event)->with('status', 'Event saved.');
    }

    public function addTableType(Request $request, Event $event)
    {
        $event->tableTypes()->create($this->spaceData($request) + ['sort_order' => $event->tableTypes()->count()]);

        return back()->with('status', 'Added.');
    }

    public function updateTableType(Request $request, TableType $tableType)
    {
        $data = $this->spaceData($request);
        $committed = Bookings::committed($tableType->id);
        if ($data['quantity'] < $committed) {
            return back()->with('error', "{$committed} of these are already booked or held, so the count can't go below that.");
        }
        $tableType->update($data);

        return back()->with('status', 'Saved.');
    }

    public function addTicketType(Request $request, Event $event)
    {
        $event->ticketTypes()->create($this->ticketData($request) + ['sort_order' => $event->ticketTypes()->count()]);

        return redirect()->route('admin.events.show', [$event, 'tab' => 'tickets'])->with('status', 'Ticket type added.');
    }

    public function updateTicketType(Request $request, TicketType $ticketType)
    {
        $ticketType->update($this->ticketData($request) + ['active' => $request->boolean('active')]);

        return redirect()->route('admin.events.show', [$ticketType->event, 'tab' => 'tickets'])->with('status', 'Saved.');
    }

    /** Add a vendor who booked by text, Facebook or in person, in one step. */
    public function walkIn(Request $request, Event $event, Bookings $bookings)
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:200'],
            'contact_name' => ['nullable', 'string', 'max:150'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'table_type_id' => ['required', Rule::exists('table_types', 'id')->where('event_id', $event->id)],
            'quantity' => ['required', 'integer', 'min:1', 'max:20'],
            'table_numbers' => ['nullable', 'string', 'max:60'],
            'amount' => ['nullable', 'string', 'max:12'],
            'method' => ['required', 'in:cash,other,unpaid'],
        ]);
        $tableType = $event->tableTypes()->findOrFail($data['table_type_id']);
        if (($data['amount'] ?? '') !== '') {
            $data['amount_cents'] = Money::parse($data['amount']);
            if ($data['amount_cents'] === null) {
                return back()->withInput()->with('error', 'That amount doesn’t look right.');
            }
        }
        $data['contact_name'] ??= '';
        $error = $bookings->addWalkIn($event, $tableType, $data, $data['method']);

        return back()->with($error ? 'error' : 'status', $error ?? "Added {$data['business_name']}.");
    }

    public function comp(Request $request, Event $event, Ticketing $ticketing)
    {
        $data = $request->validate([
            'buyer_name' => ['required', 'string', 'max:150'],
            'email' => ['required', 'email', 'max:190'],
            'ticket_type_id' => ['required', Rule::exists('ticket_types', 'id')->where('event_id', $event->id)],
            'quantity' => ['required', 'integer', 'min:1', 'max:50'],
        ]);
        [, , $error] = $ticketing->createOrder($event, $data['buyer_name'], $data['email'], [$data['ticket_type_id'] => $data['quantity']], 'comp');

        return redirect()->route('admin.events.show', [$event, 'tab' => 'tickets'])
            ->with($error ? 'error' : 'status', $error ?? 'Comp tickets sent to '.$data['email'].'.');
    }

    public function resetCheckin(Event $event)
    {
        $event->forceFill(['checkin_token' => Str::lower(Str::random(32))])->save();

        return redirect()->route('admin.events.show', [$event, 'tab' => 'door'])->with('status', 'New check-in link made. The old one no longer works.');
    }

    public function vendorsCsv(Event $event)
    {
        $rows = $event->applications()->with('tableType')->whereNotIn('status', ['checkout', 'incomplete'])->orderBy('business_name')->get();
        $filename = $event->slug.'-vendors.csv';

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Business', 'Contact', 'Email', 'Phone', 'Status', 'Space', 'Qty', 'Table numbers', 'Amount', 'Paid via', 'Paid at', 'Categories', 'Needs power', 'Checked in']);
            foreach ($rows as $a) {
                fputcsv($out, [$a->business_name, $a->contact_name, $a->email, $a->phone, $a->statusBadge()[1], $a->tableType?->name,
                    $a->quantity, $a->table_numbers, $a->amount_cents !== null ? number_format($a->amount_cents / 100, 2, '.', '') : '',
                    $a->payment_method, $a->paid_at?->format('Y-m-d H:i'), $a->categories, $a->needs_power ? 'yes' : '', $a->checked_in_at ? 'yes' : '']);
            }
            fclose($out);
        }, $filename, ['Content-Type' => 'text/csv']);
    }

    /** Money in for one event, split by source, net of platform fees. */
    private function finances(Event $event): array
    {
        $apps = $event->applications()->whereNotNull('paid_at')->get();
        $online = $apps->where('payment_method', 'online');
        $byHand = $apps->whereIn('payment_method', ['cash', 'other']);
        $orders = $event->ticketOrders()->where('status', 'paid')->where('payment_method', 'online')->get();
        $door = $event->doorSales()->where('kind', 'sale')->get();

        $m = [
            'tables_online' => (int) $online->sum('amount_cents'),
            'tables_fee' => (int) $online->sum('platform_fee_cents'),
            'tables_refunded' => (int) $apps->sum('refunded_cents'),
            'tables_cash' => (int) $byHand->sum('amount_cents'),
            'tickets_online' => (int) $orders->sum('subtotal_cents'),
            'ticket_fees_paid_by_buyers' => (int) $orders->sum('fee_cents'),
            'door_cash' => (int) $door->where('method', 'cash')->sum('amount_cents'),
            'door_card' => (int) $door->where('method', 'card')->sum('amount_cents'),
            'door_other' => (int) $door->where('method', 'other')->sum('amount_cents'),
        ];
        $m['payout'] = $m['tables_online'] - $m['tables_fee'] - $m['tables_refunded'] + $m['tickets_online'];
        $m['collected_in_person'] = $m['tables_cash'] + $m['door_cash'] + $m['door_card'] + $m['door_other'];
        $m['total'] = $m['payout'] + $m['collected_in_person'];

        return $m;
    }

    private function validated(Request $request, ?Event $event = null): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(config('event_types')))],
            'title' => ['required', 'string', 'max:200'],
            'starts_on' => ['nullable', 'date'],
            'hours' => ['nullable', 'string', 'max:80'],
            'vendor_setup' => ['nullable', 'string', 'max:120'],
            'venue_name' => ['nullable', 'string', 'max:200'],
            'venue_address' => ['nullable', 'string', 'max:255'],
            'town' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:5000'],
            'admission_note' => ['nullable', 'string', 'max:200'],
            'categories' => ['nullable', 'string', 'max:500'],
            'vendor_terms' => ['nullable', 'string', 'max:5000'],
            'status' => ['required', Rule::in(array_keys(Event::STATUSES))],
            'notice' => ['nullable', Rule::in(['', 'cancelled', 'rescheduled'])],
            'notice_text' => ['nullable', 'string', 'max:500'],
            'vendor_payment_mode' => ['required', Rule::in(array_keys(Event::PAYMENT_MODES))],
            'payment_window_days' => ['required', 'integer', 'min:1', 'max:60'],
            'ticket_capacity' => ['nullable', 'integer', 'min:1'],
        ]);
        foreach (['hours', 'vendor_setup', 'venue_name', 'venue_address', 'town', 'admission_note', 'categories', 'notice', 'notice_text'] as $k) {
            $data[$k] = $data[$k] ?? '';
        }
        $data['applications_open'] = $request->boolean('applications_open');
        $data['tickets_on_sale'] = $request->boolean('tickets_on_sale');

        return $data;
    }

    private function spaceData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'string', 'max:12'],
            'quantity' => ['required', 'integer', 'min:0', 'max:5000'],
            'max_per_vendor' => ['required', 'integer', 'min:1', 'max:50'],
        ]);
        $price = Money::parse($data['price']);
        abort_if($price === null, 422, 'Enter a price like 50 or 50.00.');

        return ['name' => $data['name'], 'description' => $data['description'] ?? '', 'price_cents' => $price,
            'quantity' => $data['quantity'], 'max_per_vendor' => $data['max_per_vendor']];
    }

    private function ticketData(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:255'],
            'price' => ['required', 'string', 'max:12'],
            'quantity' => ['required', 'integer', 'min:0', 'max:100000'],
            'max_per_order' => ['required', 'integer', 'min:1', 'max:50'],
        ]);
        $price = Money::parse($data['price']);
        abort_if($price === null, 422, 'Enter a price like 5 or 5.00.');

        return ['name' => $data['name'], 'description' => $data['description'] ?? '', 'price_cents' => $price,
            'quantity' => $data['quantity'], 'max_per_order' => $data['max_per_order']];
    }
}
