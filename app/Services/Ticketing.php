<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Models\TicketOrderItem;
use App\Models\TicketType;
use App\Services\Payments\PaymentGateway;
use App\Support\Money;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/*
 * Ticket sales, ported from the Nutmeg app's tickets.php.
 *
 * A ticket counts against inventory when its order is paid (or comped), or
 * pending with an unexpired checkout hold, so an abandoned checkout frees its
 * tickets on its own.
 */
class Ticketing
{
    // No 0/O/1/I so codes read cleanly at the door.
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private PaymentGateway $gateway) {}

    private static function committedItems()
    {
        return TicketOrderItem::query()->whereHas('order', fn ($q) => $q->where(fn ($q) => $q->where('status', 'paid')
            ->orWhere(fn ($q) => $q->where('status', 'pending')->where('hold_until', '>', now()))));
    }

    public static function soldForType(int $typeId): int
    {
        return (int) self::committedItems()->where('ticket_type_id', $typeId)->sum('quantity');
    }

    public static function soldForEvent(Event $event): int
    {
        return (int) self::committedItems()->whereIn('ticket_type_id', $event->ticketTypes()->pluck('id'))->sum('quantity');
    }

    public static function selling(Event $event): bool
    {
        return $event->isPublic() && $event->tickets_on_sale && $event->notice !== 'cancelled'
            && (! $event->starts_on || ! $event->starts_on->endOfDay()->isPast());
    }

    /** Active ticket types, each with ->sold and ->available set. */
    public static function availability(Event $event, bool $includeInactive = false)
    {
        $eventLeft = $event->ticket_capacity ? max(0, $event->ticket_capacity - self::soldForEvent($event)) : PHP_INT_MAX;

        return $event->ticketTypes()->when(! $includeInactive, fn ($q) => $q->where('active', true))->get()
            ->each(function (TicketType $t) use ($eventLeft) {
                $t->sold = self::soldForType($t->id);
                $t->available = min(max(0, $t->quantity - $t->sold), $eventLeft);
            });
    }

    public static function feeFor(int $paidTickets, string $method): int
    {
        return $method === 'online' ? $paidTickets * config('platform.fees.ticket_cents') : 0;
    }

    /**
     * Creates a pending order holding the tickets.
     * $quantities is [ticket_type_id => qty]. $method is "online" or "comp".
     * Returns [order|null, checkout URL|null, error|null].
     */
    public function createOrder(Event $event, string $name, string $email, array $quantities, string $method = 'online'): array
    {
        $quantities = array_filter(array_map('intval', $quantities), fn ($q) => $q > 0);
        if (! $quantities) {
            return [null, null, 'Choose at least one ticket.'];
        }

        $result = DB::transaction(function () use ($event, $name, $email, $quantities, $method) {
            Event::whereKey($event->id)->lockForUpdate()->first();
            $types = self::availability($event, $method === 'comp')->keyBy('id');
            $subtotal = 0;
            $paidCount = 0;
            foreach ($quantities as $typeId => $qty) {
                $type = $types->get($typeId);
                if (! $type) {
                    return 'One of those ticket types is no longer available.';
                }
                if ($method !== 'comp' && $qty > $type->max_per_order) {
                    return "You can buy up to {$type->max_per_order} {$type->name} tickets per order.";
                }
                if ($qty > $type->available) {
                    return $type->available > 0 ? "Only {$type->available} {$type->name} tickets left." : "{$type->name} is sold out.";
                }
                if ($method !== 'comp') {
                    $subtotal += $type->price_cents * $qty;
                    $paidCount += $type->price_cents > 0 ? $qty : 0;
                }
            }
            $fee = self::feeFor($paidCount, $method);
            $order = new TicketOrder([
                'event_id' => $event->id,
                'buyer_name' => $name,
                'email' => Str::lower($email),
                'status' => 'pending',
                'subtotal_cents' => $subtotal,
                'fee_cents' => $fee,
                'total_cents' => $subtotal + $fee,
                'hold_until' => now()->addMinutes(config('platform.checkout_hold_minutes')),
            ]);
            $order->order_token = Str::random(40);
            $order->save();
            foreach ($quantities as $typeId => $qty) {
                $order->items()->create([
                    'ticket_type_id' => $typeId,
                    'quantity' => $qty,
                    'unit_price_cents' => $method === 'comp' ? 0 : $types->get($typeId)->price_cents,
                ]);
            }

            return $order;
        });

        if (is_string($result)) {
            return [null, null, $result];
        }
        if ($method === 'comp' || $result->total_cents === 0) {
            $this->fulfill($result, $method === 'comp' ? 'comp' : 'free');

            return [$result->fresh(), null, null];
        }

        $url = $this->gateway->checkoutUrl('order', $result->order_token, $result->total_cents, $result->fee_cents,
            $event->title.': '.$result->items->sum('quantity').' tickets');

        return [$result, $url, null];
    }

    /** Marks an order paid and issues its tickets. Safe to call twice. */
    public function fulfill(TicketOrder $order, string $method, ?string $reference = null): bool
    {
        $done = DB::transaction(function () use ($order, $method, $reference) {
            $order = TicketOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();
            if ($order->status === 'paid') {
                return false;
            }
            $order->update(['status' => 'paid', 'paid_at' => now(), 'payment_method' => $method,
                'payment_reference' => $reference, 'hold_until' => null]);
            foreach ($order->items as $item) {
                for ($i = 0; $i < $item->quantity; $i++) {
                    Ticket::create([
                        'ticket_order_id' => $order->id,
                        'event_id' => $order->event_id,
                        'ticket_type_id' => $item->ticket_type_id,
                        'code' => self::newCode(),
                    ]);
                }
            }

            return true;
        });
        if ($done) {
            $this->emailTickets($order->fresh());
        }

        return $done;
    }

    public function expire(TicketOrder $order): void
    {
        TicketOrder::whereKey($order->id)->where('status', 'pending')->update(['status' => 'expired', 'hold_until' => null]);
    }

    public static function newCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 8; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (Ticket::withoutGlobalScopes()->where('code', $code)->exists());

        return $code;
    }

    private function emailTickets(TicketOrder $order): void
    {
        $event = $order->event;
        $count = $order->tickets()->count();
        Notifier::send($event->organizer, $order->email, "Your tickets for {$event->title}", [
            "Hi {$order->buyer_name},",
            "You're all set: {$count} ".Str::plural('ticket', $count)." for {$event->title}.",
            'Event: '.$event->whenWhere().($order->total_cents ? "\nPaid: ".Money::format($order->total_cents) : ''),
            'Show the QR codes on your phone at the door, or give your name.',
        ], ['label' => 'View my tickets', 'url' => $event->organizer->url('/orders/'.$order->order_token)]);
    }
}
