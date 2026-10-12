<?php

namespace Tests\Feature;

use App\Models\Vendor;
use App\Services\Bookings;
use App\Services\VendorAuth;
use App\Tenancy\Tenancy;
use Tests\TestCase;

class DirectoryTest extends TestCase
{
    public function test_lists_published_events_from_every_organizer_only(): void
    {
        $a = $this->organizer('nutmeg');
        $b = $this->organizer('quarryhill');
        $this->event($a, ['title' => 'Holiday Card Show', 'town' => 'Watertown']);
        $this->event($b, ['title' => 'Holiday Craft Fair', 'type' => 'craft_fair', 'town' => 'Middlebury']);
        $this->event($b, ['title' => 'Secret Draft Fair', 'status' => 'draft']);
        $this->event($a, ['title' => 'Last Year Show', 'starts_on' => now()->subYear()->toDateString()]);

        $this->get($this->directory('/'))->assertOk()
            ->assertSee('Holiday Card Show')->assertSee('Holiday Craft Fair')
            ->assertDontSee('Secret Draft Fair')->assertDontSee('Last Year Show');

        $this->get($this->directory('/?type=craft_fair'))->assertSee('Holiday Craft Fair')->assertDontSee('Holiday Card Show');
        $this->get($this->directory('/?town=water'))->assertSee('Holiday Card Show')->assertDontSee('Holiday Craft Fair');
    }

    public function test_a_draft_event_page_is_hidden_from_the_public(): void
    {
        $org = $this->organizer('nutmeg');
        $event = $this->event($org, ['title' => 'Draft Show', 'status' => 'draft']);
        $this->get($this->site($org, "/events/{$event->slug}"))->assertNotFound();

        $this->actingAs($this->staff($org));
        $this->get($this->site($org, "/events/{$event->slug}"))->assertOk()->assertSee('Preview');
    }

    public function test_vendor_profile_shows_where_they_will_be_next_across_organizers(): void
    {
        $a = $this->organizer('nutmeg');
        $b = $this->organizer('valleyflea');
        $ea = $this->event($a, ['title' => 'Card Show', 'vendor_payment_mode' => 'instant']);
        $eb = $this->event($b, ['title' => 'Flea Market', 'vendor_payment_mode' => 'instant']);
        $bookings = app(Bookings::class);
        foreach ([[$a, $ea], [$b, $eb]] as [$org, $event]) {
            Tenancy::as($org, function () use ($bookings, $event) {
                [$app] = $bookings->apply($event, $event->tableTypes()->first(), $this->applyData($event));
                $bookings->markPaid($app, 'online');
            });
        }

        $vendor = Vendor::firstOrFail();
        $this->get($this->directory('/vendors'))->assertSee('Cardboard Kings');
        $this->get($this->directory("/vendors/{$vendor->slug}"))->assertOk()->assertSee('Card Show')->assertSee('Flea Market');

        // Both organizers' event pages list the vendor as coming.
        $this->get($this->site($a, "/events/{$ea->slug}"))->assertSee('Cardboard Kings');

        // Unlisting hides the profile.
        $vendor->update(['listed' => false]);
        $this->get($this->directory("/vendors/{$vendor->slug}"))->assertNotFound();
        $this->get($this->directory('/vendors'))->assertDontSee('Cardboard Kings');
    }

    public function test_vendor_signs_in_with_an_emailed_link_and_sees_their_bookings(): void
    {
        $org = $this->organizer('nutmeg');
        $event = $this->event($org, ['vendor_payment_mode' => 'approve_first']);
        $this->post($this->site($org, "/events/{$event->slug}/apply"), $this->applyData($event));

        $url = VendorAuth::sendLink('mike@example.com', '127.0.0.1');
        $token = basename($url);
        $this->get($this->directory("/vendor/login/$token"))->assertOk();   // just a confirm button
        $this->post($this->directory("/vendor/login/$token"))->assertRedirect($this->directory('/vendor/account'));
        $this->get($this->directory('/vendor/account'))->assertOk()->assertSee('Test Show')->assertSee('Pending review');

        // Links work once.
        $this->post($this->directory('/vendor/logout'));
        $this->post($this->directory("/vendor/login/$token"))->assertRedirect($this->directory('/vendor/login'));
    }

    public function test_event_page_has_a_facebook_preview(): void
    {
        $org = $this->organizer('nutmeg');
        $event = $this->event($org, ['title' => 'Holiday Card Show']);
        $this->get($this->site($org, "/events/{$event->slug}"))
            ->assertSee('<meta property="og:title" content="Holiday Card Show">', false);
    }
}
