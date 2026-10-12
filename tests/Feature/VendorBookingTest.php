<?php

namespace Tests\Feature;

use App\Models\Vendor;
use App\Models\VendorApplication;
use App\Tenancy\Tenancy;
use Tests\TestCase;

/** The three payment modes and inventory rules ported from the Nutmeg app. */
class VendorBookingTest extends TestCase
{
    private function app(string $email = 'mike@example.com'): VendorApplication
    {
        return Tenancy::acrossOrganizers(fn () => VendorApplication::where('email', $email)->latest('id')->firstOrFail());
    }

    private function payAt(string $url): void
    {
        // Follow the test checkout and press Pay.
        $path = parse_url($url, PHP_URL_PATH);
        $host = parse_url($url, PHP_URL_HOST);
        $this->post("http://{$host}{$path}", ['action' => 'pay'])->assertRedirect();
    }

    public function test_review_mode_holds_the_space_while_paying_then_waits_for_approval(): void
    {
        $org = $this->organizer('nutmeg');
        $event = $this->event($org, ['vendor_payment_mode' => 'review']);

        $res = $this->post($this->site($org, "/events/{$event->slug}/apply"), $this->applyData($event, ['quantity' => 2]));
        $res->assertRedirectContains('/test-checkout/booking/');
        $app = $this->app();
        $this->assertSame('checkout', $app->status);
        $this->assertSame(10000, $app->amount_cents);

        $this->payAt($res->headers->get('Location'));
        $app = $this->app();
        $this->assertTrue($app->paidAwaitingReview());
        $this->assertSame(500, $app->platform_fee_cents); // 5% of $100

        $this->actingAs($this->staff($org));
        $this->post($this->site($org, "/admin/applications/{$app->id}"), ['action' => 'approve'])->assertSessionHas('status');
        $this->assertSame('paid', $this->app()->status);
    }

    public function test_declining_a_paid_vendor_refunds_them(): void
    {
        $org = $this->organizer('nutmeg');
        $event = $this->event($org);
        $res = $this->post($this->site($org, "/events/{$event->slug}/apply"), $this->applyData($event));
        $this->payAt($res->headers->get('Location'));

        $this->actingAs($this->staff($org));
        $this->post($this->site($org, '/admin/applications/'.$this->app()->id), ['action' => 'decline']);
        $app = $this->app();
        $this->assertSame('declined', $app->status);
        $this->assertSame(5000, $app->refunded_cents);
    }

    public function test_instant_mode_confirms_on_payment(): void
    {
        $org = $this->organizer('valleyflea');
        $event = $this->event($org, ['vendor_payment_mode' => 'instant']);
        $res = $this->post($this->site($org, "/events/{$event->slug}/apply"), $this->applyData($event));
        $this->payAt($res->headers->get('Location'));

        $this->assertSame('paid', $this->app()->status);
    }

    public function test_approve_first_mode_applies_free_then_pays_from_a_link(): void
    {
        $org = $this->organizer('quarryhill');
        $event = $this->event($org, ['vendor_payment_mode' => 'approve_first']);

        $this->post($this->site($org, "/events/{$event->slug}/apply"), $this->applyData($event))
            ->assertRedirectContains('/booking/');
        $app = $this->app();
        $this->assertSame('pending', $app->status);
        $this->assertNull($app->paid_at);

        $this->actingAs($this->staff($org));
        $this->post($this->site($org, "/admin/applications/{$app->id}"), ['action' => 'approve']);
        $app = $this->app();
        $this->assertSame('approved', $app->status);
        $this->assertNotNull($app->pay_by);

        $res = $this->post($this->site($org, "/booking/{$app->pay_token}/pay"));
        $this->payAt($res->headers->get('Location'));
        $this->assertSame('paid', $this->app()->status);
    }

    public function test_a_full_space_type_puts_new_vendors_on_the_waitlist(): void
    {
        $org = $this->organizer('nutmeg');
        $event = $this->event($org, ['vendor_payment_mode' => 'instant']);
        Tenancy::as($org, fn () => $event->tableTypes()->first()->update(['quantity' => 1]));

        $this->post($this->site($org, "/events/{$event->slug}/apply"), $this->applyData($event, ['email' => 'first@example.com']));
        $this->assertSame('checkout', $this->app('first@example.com')->status);

        // The first vendor's checkout hold still counts, so the second is waitlisted.
        $this->post($this->site($org, "/events/{$event->slug}/apply"), $this->applyData($event, ['email' => 'second@example.com']));
        $this->assertSame('waitlisted', $this->app('second@example.com')->status);
    }

    public function test_an_abandoned_checkout_releases_the_space(): void
    {
        $org = $this->organizer('nutmeg');
        $event = $this->event($org, ['vendor_payment_mode' => 'instant']);
        Tenancy::as($org, fn () => $event->tableTypes()->first()->update(['quantity' => 1]));

        $this->post($this->site($org, "/events/{$event->slug}/apply"), $this->applyData($event, ['email' => 'first@example.com']));
        $this->travel(36)->minutes();

        $this->post($this->site($org, "/events/{$event->slug}/apply"), $this->applyData($event, ['email' => 'second@example.com']));
        $this->assertSame('checkout', $this->app('second@example.com')->status);
    }

    public function test_cannot_book_more_than_the_per_vendor_maximum(): void
    {
        $org = $this->organizer('nutmeg');
        $event = $this->event($org);
        $this->post($this->site($org, "/events/{$event->slug}/apply"), $this->applyData($event, ['quantity' => 3]))
            ->assertSessionHasErrors('quantity');
    }

    public function test_organizer_adds_a_cash_vendor_in_one_step(): void
    {
        $org = $this->organizer('nutmeg');
        $event = $this->event($org);
        $this->actingAs($this->staff($org));
        $tt = Tenancy::as($org, fn () => $event->tableTypes()->first());

        $this->post($this->site($org, "/admin/events/{$event->slug}/walk-in"), [
            'business_name' => "Gary's Cards", 'table_type_id' => $tt->id, 'quantity' => 1, 'table_numbers' => '30', 'method' => 'cash',
        ])->assertSessionHas('status');

        $app = Tenancy::as($org, fn () => VendorApplication::firstOrFail());
        $this->assertSame('paid', $app->status);
        $this->assertSame('cash', $app->payment_method);
        $this->assertSame(0, $app->platform_fee_cents); // no fee on cash
        $this->assertSame('30', $app->table_numbers);
    }

    public function test_one_vendor_account_spans_organizers(): void
    {
        $a = $this->organizer('nutmeg');
        $b = $this->organizer('quarryhill');
        $ea = $this->event($a, ['vendor_payment_mode' => 'approve_first', 'title' => 'Card Show']);
        $eb = $this->event($b, ['vendor_payment_mode' => 'approve_first', 'title' => 'Craft Fair']);

        $this->post($this->site($a, "/events/{$ea->slug}/apply"), $this->applyData($ea));
        $this->post($this->site($b, "/events/{$eb->slug}/apply"), $this->applyData($eb));

        $this->assertSame(1, Vendor::count());
        $vendor = Vendor::first();
        $this->assertSame(2, Tenancy::acrossOrganizers(fn () => VendorApplication::where('vendor_id', $vendor->id)->count()));
    }
}
