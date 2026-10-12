<?php

namespace Tests\Feature;

use App\Models\DoorSale;
use App\Models\Ticket;
use App\Models\TicketOrder;
use App\Tenancy\Tenancy;
use Tests\TestCase;

class TicketsAndDoorTest extends TestCase
{
    public function test_buying_tickets_adds_the_platform_fee_and_issues_codes(): void
    {
        $org = $this->organizer('nutmeg');
        $event = $this->event($org);
        $type = Tenancy::as($org, fn () => $event->ticketTypes()->first());

        $res = $this->post($this->site($org, "/events/{$event->slug}/tickets"), [
            'buyer_name' => 'Jamie', 'email' => 'jamie@example.com', 'qty' => [$type->id => 2],
        ]);
        $res->assertRedirectContains('/test-checkout/order/');
        $order = Tenancy::as($org, fn () => TicketOrder::firstOrFail());
        $this->assertSame(1000, $order->subtotal_cents);
        $this->assertSame(150, $order->fee_cents);     // 2 × $0.75
        $this->assertSame(1150, $order->total_cents);

        $this->post($this->site($org, "/test-checkout/order/{$order->order_token}"), ['action' => 'pay'])
            ->assertRedirect($this->site($org, "/orders/{$order->order_token}"));

        $page = $this->get($this->site($org, "/orders/{$order->order_token}"))->assertOk();
        $codes = Tenancy::as($org, fn () => Ticket::pluck('code'));
        $this->assertCount(2, $codes);
        $page->assertSee(implode('-', str_split($codes[0], 4)));

        $this->get($this->site($org, "/orders/{$order->order_token}/qr/{$codes[0]}.svg"))
            ->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    }

    public function test_capacity_is_enforced(): void
    {
        $org = $this->organizer('nutmeg');
        $event = $this->event($org);
        $type = Tenancy::as($org, function () use ($event) {
            $t = $event->ticketTypes()->first();
            $t->update(['quantity' => 3]);

            return $t;
        });

        $this->post($this->site($org, "/events/{$event->slug}/tickets"), [
            'buyer_name' => 'Jamie', 'email' => 'jamie@example.com', 'qty' => [$type->id => 4],
        ])->assertSessionHas('error', 'Only 3 General tickets left.');
    }

    public function test_door_check_in_scans_a_ticket_once(): void
    {
        $org = $this->organizer('nutmeg');
        $event = $this->event($org);
        $type = Tenancy::as($org, fn () => $event->ticketTypes()->first());
        $this->post($this->site($org, "/events/{$event->slug}/tickets"), ['buyer_name' => 'Pat', 'email' => 'pat@example.com', 'qty' => [$type->id => 1]]);
        $order = Tenancy::as($org, fn () => TicketOrder::firstOrFail());
        $this->post($this->site($org, "/test-checkout/order/{$order->order_token}"), ['action' => 'pay']);
        $code = Tenancy::as($org, fn () => Ticket::firstOrFail()->code);
        $door = $this->site($org, "/checkin/{$event->checkin_token}");

        $this->post("$door/scan", ['code' => strtolower(substr($code, 0, 4).'-'.substr($code, 4))])
            ->assertSessionHas('scan', fn ($s) => $s['tone'] === 'ok');
        $this->post("$door/scan", ['code' => $code])->assertSessionHas('scan', fn ($s) => $s['title'] === 'Already checked in');
        $this->post("$door/scan", ['code' => 'ZZZZZZZZ'])->assertSessionHas('scan', fn ($s) => $s['tone'] === 'bad');
        $this->post("$door/scan", ['code' => 'pat@'])->assertSessionHas('matches');
    }

    public function test_door_sales_and_kids_are_counted(): void
    {
        $org = $this->organizer('nutmeg');
        $event = $this->event($org);
        $type = Tenancy::as($org, fn () => $event->ticketTypes()->first());
        $door = $this->site($org, "/checkin/{$event->checkin_token}");

        $this->post("$door/door", ['kind' => 'sale', 'ticket_type_id' => $type->id, 'quantity' => 2, 'method' => 'cash']);
        $this->post("$door/door", ['kind' => 'sale', 'quantity' => 1, 'method' => 'card', 'amount' => '7.50']);
        $this->post("$door/door", ['kind' => 'kids', 'quantity' => 3]);

        Tenancy::as($org, function () {
            $this->assertSame(1000, (int) DoorSale::where('method', 'cash')->sum('amount_cents'));
            $this->assertSame(750, (int) DoorSale::where('method', 'card')->sum('amount_cents'));
            $this->assertSame(3, (int) DoorSale::where('kind', 'kids')->sum('quantity'));
        });

        $this->get("$door?tab=door")->assertOk()->assertSee('In the door')->assertSee('$10');

        $this->post("$door/door/undo");
        Tenancy::as($org, fn () => $this->assertSame(0, (int) DoorSale::where('kind', 'kids')->count()));
    }

    public function test_resetting_the_check_in_link_cuts_off_the_old_one(): void
    {
        $org = $this->organizer('nutmeg');
        $event = $this->event($org);
        $old = $event->checkin_token;
        $this->actingAs($this->staff($org));

        $this->post($this->site($org, "/admin/events/{$event->slug}/checkin-link"));
        $this->get($this->site($org, "/checkin/$old"))->assertNotFound();
    }
}
