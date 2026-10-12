<?php

namespace App\Services;

use App\Models\DoorSale;
use App\Models\Event;
use App\Models\TicketOrder;
use App\Models\VendorApplication;
use Illuminate\Support\Collection;

/**
 * Money in, by event and by source, net of platform fees. "Paid out" is what
 * the payment processor would send the organizer; "in person" is cash, card
 * readers and other money collected at the event.
 */
class Finances
{
    public static function forEvent(Event $event): array
    {
        return self::summarize(
            VendorApplication::where('event_id', $event->id)->whereNotNull('paid_at')->get(),
            TicketOrder::with('items')->where('event_id', $event->id)->where('status', 'paid')->get(),
            DoorSale::where('event_id', $event->id)->where('kind', 'sale')->get(),
        );
    }

    /** One row per event (newest first) plus totals and a by-month series. */
    public static function forOrganizer(?string $from = null, ?string $to = null): array
    {
        $events = Event::query()
            ->when($from, fn ($q) => $q->where('starts_on', '>=', $from))
            ->when($to, fn ($q) => $q->where('starts_on', '<=', $to))
            ->orderByDesc('starts_on')->get();
        $ids = $events->pluck('id');
        $apps = VendorApplication::whereIn('event_id', $ids)->whereNotNull('paid_at')->get()->groupBy('event_id');
        $orders = TicketOrder::with('items')->whereIn('event_id', $ids)->where('status', 'paid')->get()->groupBy('event_id');
        $door = DoorSale::whereIn('event_id', $ids)->where('kind', 'sale')->get()->groupBy('event_id');
        $empty = collect();

        $rows = $events->map(fn ($e) => ['event' => $e] + self::summarize($apps[$e->id] ?? $empty, $orders[$e->id] ?? $empty, $door[$e->id] ?? $empty));
        $keys = array_keys(self::summarize($empty, $empty, $empty));
        $totals = [];
        foreach ($keys as $k) {
            $totals[$k] = (int) $rows->sum($k);
        }
        $byMonth = $rows->filter(fn ($r) => $r['event']->starts_on)
            ->groupBy(fn ($r) => $r['event']->starts_on->format('Y-m'))
            ->map(fn ($g) => (int) $g->sum('total'))->sortKeys();

        return ['rows' => $rows, 'totals' => $totals, 'byMonth' => $byMonth];
    }

    private static function summarize(Collection $apps, Collection $orders, Collection $door): array
    {
        $online = $apps->where('payment_method', 'online');
        $byHand = $apps->whereIn('payment_method', ['cash', 'other']);
        $onlineOrders = $orders->where('payment_method', 'online');

        $m = [
            'vendors_paid' => $apps->count(),
            'tables_online' => (int) $online->sum('amount_cents'),
            'tables_fee' => (int) $online->sum('platform_fee_cents'),
            'tables_refunded' => (int) $apps->sum('refunded_cents'),
            'tables_cash' => (int) $byHand->sum('amount_cents'),
            'tickets_sold' => (int) $orders->sum(fn ($o) => $o->items->sum('quantity')),
            'tickets_online' => (int) $onlineOrders->sum('subtotal_cents'),
            'ticket_fees_paid_by_buyers' => (int) $onlineOrders->sum('fee_cents'),
            'door_count' => (int) $door->sum('quantity'),
            'door_cash' => (int) $door->where('method', 'cash')->sum('amount_cents'),
            'door_card' => (int) $door->where('method', 'card')->sum('amount_cents'),
            'door_other' => (int) $door->where('method', 'other')->sum('amount_cents'),
        ];
        $m['payout'] = $m['tables_online'] - $m['tables_fee'] - $m['tables_refunded'] + $m['tickets_online'];
        $m['collected_in_person'] = $m['tables_cash'] + $m['door_cash'] + $m['door_card'] + $m['door_other'];
        $m['total'] = $m['payout'] + $m['collected_in_person'];

        return $m;
    }
}
