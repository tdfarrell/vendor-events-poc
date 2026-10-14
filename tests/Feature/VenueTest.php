<?php

namespace Tests\Feature;

use App\Models\EmailLog;
use App\Models\Organizer;
use App\Models\PlatformAdmin;
use App\Models\Venue;
use App\Models\VenueClaim;
use App\Models\VenueInquiry;
use App\Models\VenueRoom;
use App\Tenancy\Tenancy;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class VenueTest extends TestCase
{
    private function venue(?Organizer $org, array $attrs = [], array $rooms = [['name' => 'Main hall', 'seated' => 150, 'standing' => 200, 'price_from' => 400, 'amenities' => ['kitchen', 'bar']]]): Venue
    {
        $v = new Venue($attrs + ['name' => 'Test Hall', 'kind' => 'veterans_hall', 'town' => 'Torrington',
            'contact_email' => 'hall@example.com', 'status' => $org ? 'active' : 'listed']);
        $v->organizer_id = $org?->id;
        $v->slug = Venue::uniqueSlug($v->name, $v->town);
        $v->save();
        foreach ($rooms as $r) {
            $v->rooms()->create($r + ['price_unit' => 'event']);
        }

        return $v->fresh('rooms');
    }

    private function inquiry(Venue $v, array $attrs = []): VenueInquiry
    {
        $i = new VenueInquiry($attrs + ['name' => 'Maria', 'email' => 'maria@example.com', 'event_kind' => 'birthday']);
        $i->venue_id = $v->id;
        $i->save();

        return $i;
    }

    private function ask(array $overrides = []): array
    {
        return $overrides + ['name' => 'Maria Lopez', 'email' => 'maria@example.com', 'event_kind' => 'birthday',
            'event_date' => now()->addMonth()->toDateString(), 'guests' => 120, 'message' => 'Can we bring a caterer?'];
    }

    public function test_directory_lists_public_venues_and_filters_them(): void
    {
        $river = $this->organizer('riverside');
        $pending = $this->organizer('newhall', ['status' => 'pending']);
        $this->venue($river, ['name' => 'Riverside Veterans Hall']);
        $this->venue(null, ['name' => 'Grace Fellowship Hall', 'kind' => 'church_hall', 'town' => 'Naugatuck'],
            [['name' => 'Fellowship hall', 'seated' => 60, 'amenities' => ['kitchen']]]);
        $this->venue(null, ['name' => 'Hidden Barn', 'status' => 'hidden']);
        $this->venue($pending, ['name' => 'Not Live Yet Hall']);

        $this->get($this->directory('/venues'))->assertOk()
            ->assertSee('Riverside Veterans Hall')->assertSee('Grace Fellowship Hall')
            ->assertDontSee('Hidden Barn')->assertDontSee('Not Live Yet Hall');
        $this->get($this->directory('/venues?guests=100'))->assertSee('Riverside Veterans Hall')->assertDontSee('Grace Fellowship Hall');
        $this->get($this->directory('/venues?kind=church_hall'))->assertSee('Grace Fellowship Hall')->assertDontSee('Riverside Veterans Hall');
        $this->get($this->directory('/venues?amenity=bar'))->assertSee('Riverside Veterans Hall')->assertDontSee('Grace Fellowship Hall');
        $this->get($this->directory('/venues?town=nauga'))->assertSee('Grace Fellowship Hall')->assertDontSee('Riverside Veterans Hall');

        $this->get($this->directory('/venues/hidden-barn-torrington'))->assertNotFound();
        $this->get($this->directory('/venues/not-live-yet-hall-torrington'))->assertNotFound();
    }

    public function test_a_rental_request_reaches_the_venue_organizer_and_the_renter(): void
    {
        $river = $this->organizer('riverside');
        $v = $this->venue($river, ['name' => 'Riverside Veterans Hall']);

        $this->get($this->directory("/venues/{$v->slug}"))->assertOk()->assertSee('Main hall')->assertSee('$400 per event')->assertSee('Ask about a date');
        $this->post($this->directory("/venues/{$v->slug}/ask"), $this->ask(['venue_room_id' => $v->rooms[0]->id]))
            ->assertRedirect()->assertSessionHas('status');

        $i = VenueInquiry::sole();
        $this->assertSame('new', $i->status);
        $this->assertSame($v->rooms[0]->id, $i->venue_room_id);
        $logged = Tenancy::as($river, fn () => EmailLog::where('kind', 'inquiry')->pluck('to_email')->all());
        $this->assertEqualsCanonicalizing(['hall@example.com', 'maria@example.com'], $logged);
    }

    public function test_requests_to_an_unclaimed_venue_carry_a_claim_link(): void
    {
        $v = $this->venue(null, ['name' => 'Grace Fellowship Hall', 'contact_email' => 'office@grace.example']);
        $this->post($this->directory("/venues/{$v->slug}/ask"), $this->ask())->assertRedirect();

        $mail = DB::table('email_log')->where('to_email', 'office@grace.example')->first();
        $this->assertNotNull($mail);
        $this->assertNull($mail->organizer_id);
        $this->assertStringContainsString('Claim your free listing', $mail->body);
    }

    public function test_requests_are_validated(): void
    {
        $v = $this->venue(null);
        $other = $this->venue(null, ['name' => 'Other Hall']);

        $this->post($this->directory("/venues/{$v->slug}/ask"), $this->ask(['event_date' => now()->subDay()->toDateString()]))->assertSessionHasErrors('event_date');
        $this->post($this->directory("/venues/{$v->slug}/ask"), $this->ask(['venue_room_id' => $other->rooms[0]->id]))->assertSessionHasErrors('venue_room_id');
        $this->post($this->directory("/venues/{$v->slug}/ask"), $this->ask(['company_website' => 'spam']));
        $this->assertSame(0, VenueInquiry::count());
    }

    public function test_one_organizer_cannot_reach_another_organizers_venue_or_requests(): void
    {
        $a = $this->organizer('riverside');
        $b = $this->organizer('nutmeg');
        $va = $this->venue($a, ['name' => 'Riverside Veterans Hall']);
        $ia = $this->inquiry($va, ['name' => 'Secret Renter']);
        $unclaimed = $this->venue(null, ['name' => 'Unclaimed Hall']);
        $this->inquiry($unclaimed, ['name' => 'Unclaimed Renter']);

        $this->actingAs($this->staff($b));
        $this->get($this->site($b, "/admin/venues/{$va->id}/edit"))->assertNotFound();
        $this->get($this->site($b, "/admin/venues/{$unclaimed->id}/edit"))->assertNotFound();
        $this->get($this->site($b, "/admin/inquiries/{$ia->id}"))->assertNotFound();
        $this->post($this->site($b, "/admin/inquiries/{$ia->id}"), ['status' => 'closed'])->assertNotFound();
        $this->put($this->site($b, "/admin/rooms/{$va->rooms[0]->id}"), ['name' => 'Hacked', 'price_unit' => 'event'])->assertNotFound();
        $this->post($this->site($b, "/admin/rooms/{$va->rooms[0]->id}/delete"))->assertNotFound();
        $this->post($this->site($b, "/admin/venues/{$va->id}/rooms"), ['name' => 'Sneaky', 'price_unit' => 'event'])->assertNotFound();
        $this->get($this->site($b, '/admin/inquiries'))->assertOk()->assertDontSee('Secret Renter')->assertDontSee('Unclaimed Renter');
        $this->get($this->site($b, '/admin/venue'))->assertOk()->assertDontSee('Riverside Veterans Hall');

        $this->assertSame('Main hall', $va->rooms[0]->fresh()->name);
        $this->assertSame('new', $ia->fresh()->status);

        // With no organizer set, the owned scope returns nothing.
        Tenancy::forget();
        $this->assertSame(0, Venue::ownedByCurrent()->count());
        Tenancy::as($a, fn () => $this->assertSame([$va->id], Venue::ownedByCurrent()->pluck('id')->all()));
    }

    public function test_an_organizer_lists_a_venue_adds_rooms_and_replies(): void
    {
        $org = $this->organizer('riverside', ['town' => 'Torrington']);
        $this->actingAs($this->staff($org));

        $this->get($this->site($org, '/admin/venue'))->assertOk()->assertSee('List your venue');
        $this->post($this->site($org, '/admin/venues'), [
            'name' => 'Riverside Veterans Hall', 'kind' => 'veterans_hall', 'town' => 'Torrington', 'contact_email' => 'hall@riverside.test',
            'rules' => 'No outside alcohol.',
        ])->assertRedirect();
        $v = Venue::sole();
        $this->assertSame($org->id, $v->organizer_id);
        $this->assertSame('active', $v->status);

        $this->post($this->site($org, "/admin/venues/{$v->id}/rooms"), [
            'name' => 'Canteen', 'seated' => 50, 'price_from' => 175, 'price_unit' => 'event', 'amenities' => ['kitchen', 'wifi'],
        ])->assertRedirect();
        $room = VenueRoom::sole();
        $this->assertSame(['kitchen', 'wifi'], $room->amenities);
        $this->get($this->directory("/venues/{$v->slug}"))->assertSee('Canteen')->assertSee('No outside alcohol.');

        $i = $this->inquiry($v);
        $this->get($this->site($org, '/admin/inquiries'))->assertSee('Maria');
        $this->post($this->site($org, "/admin/inquiries/{$i->id}"), ['reply' => 'The 14th is open. It is $175.'])->assertRedirect();
        $this->assertSame('replied', $i->fresh()->status);
        $this->assertNotNull($i->fresh()->replied_at);
        $reply = Tenancy::as($org, fn () => EmailLog::where('kind', 'inquiry_reply')->sole());
        $this->assertSame('maria@example.com', $reply->to_email);
        $this->assertStringContainsString('The 14th is open.', $reply->body);

        $this->post($this->site($org, "/admin/inquiries/{$i->id}"), ['status' => 'booked']);
        $this->assertSame('booked', $i->fresh()->status);
    }

    public function test_a_claimed_venue_moves_into_the_organizers_account(): void
    {
        $org = $this->organizer('maplestreet');
        $v = $this->venue(null, ['name' => 'Maple Street Community Center', 'town' => 'Watertown']);

        $this->post($this->directory("/venues/{$v->slug}/claim"), ['name' => 'Linda', 'email' => 'linda@example.com', 'role' => 'Director'])->assertRedirect();
        $claim = VenueClaim::sole();

        $admin = PlatformAdmin::create(['name' => 'Admin', 'email' => 'admin@lvh.me', 'password' => 'password']);
        $this->actingAs($admin, 'platform');
        $this->get($this->directory('/platform'))->assertOk()->assertSee('Venue claims to review')->assertSee('Maple Street Community Center');
        $this->post($this->directory("/platform/venue-claims/{$claim->id}"), ['decision' => 'approve', 'organizer_id' => $org->id])->assertRedirect();

        $v->refresh();
        $this->assertSame($org->id, $v->organizer_id);
        $this->assertSame('active', $v->status);
        $this->assertSame('approved', $claim->fresh()->status);
        Tenancy::as($org, fn () => $this->assertSame(1, Venue::ownedByCurrent()->count()));
        // Claimed venues can no longer be edited from platform admin.
        $this->get($this->directory("/platform/venues/{$v->id}/edit"))->assertNotFound();
    }

    public function test_events_held_at_a_venue_show_on_its_page(): void
    {
        $org = $this->organizer('riverside');
        $v = $this->venue(null, ['name' => 'Pomperaug Grange Hall', 'town' => 'Woodbury', 'address' => '10 Main St']);
        $this->actingAs($this->staff($org));
        $event = $this->event($org, ['title' => 'Grange Holiday Fair', 'town' => '']);

        $this->put($this->site($org, "/admin/events/{$event->slug}"), [
            'type' => 'craft_fair', 'title' => 'Grange Holiday Fair', 'status' => 'published', 'vendor_payment_mode' => 'review',
            'payment_window_days' => 7, 'venue_id' => $v->id,
        ])->assertRedirect();
        $event = Tenancy::as($org, fn () => $event->fresh());
        $this->assertSame($v->id, $event->venue_id);
        $this->assertSame('Pomperaug Grange Hall', $event->venue_name);
        $this->assertSame('Woodbury', $event->town);

        $this->get($this->directory("/venues/{$v->slug}"))->assertSee('Coming up here')->assertSee('Grange Holiday Fair');
        $this->get($this->site($org, "/events/{$event->slug}"))->assertSee('About the venue');
    }
}
