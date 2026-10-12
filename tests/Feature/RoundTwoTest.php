<?php

namespace Tests\Feature;

use App\Models\EmailLog;
use App\Models\Event;
use App\Models\FloorPlan;
use App\Models\Listing;
use App\Models\ListingClaim;
use App\Models\Organizer;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Models\VendorApplication;
use App\Services\Bookings;
use App\Support\Uploads;
use App\Tenancy\Tenancy;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/** Floor plans, duplicate, photos, messaging, email log, financials, listings, sign-up and platform admin. */
class RoundTwoTest extends TestCase
{
    private function confirmedVendor(Organizer $org, Event $event, string $email = 'mike@example.com', string $name = 'Cardboard Kings'): VendorApplication
    {
        return Tenancy::as($org, function () use ($event, $email, $name) {
            $b = app(Bookings::class);
            [$app] = $b->apply($event, $event->tableTypes()->first(), $this->applyData($event, ['email' => $email, 'business_name' => $name]));
            $b->markPaid($app, 'online');
            $b->approve($app->fresh());

            return $app->fresh();
        });
    }

    private function table(string $id, string $num, int $type, float $x): array
    {
        return ['id' => $id, 'kind' => 'table6', 'x' => $x, 'y' => 5, 'w' => 6, 'h' => 2.5, 'rot' => 0, 'num' => $num, 'type' => $type];
    }

    public function test_floor_plan_saves_layout_and_seats_vendors(): void
    {
        $org = $this->organizer('nutmeg');
        $event = $this->event($org);
        $app = $this->confirmedVendor($org, $event);
        $type = Tenancy::as($org, fn () => $event->tableTypes()->first()->id);
        $this->actingAs($this->staff($org));

        $payload = ['width' => 40, 'depth' => 20, 'aisle' => 4, 'public' => true,
            'elements' => [$this->table('a', '1', $type, 2), $this->table('b', '2', $type, 8)],
            'assignments' => [(string) $app->id => ['2']]];
        $this->post($this->site($org, "/admin/events/{$event->slug}/floor-plan"), ['payload' => json_encode($payload)])
            ->assertOk()->assertJson(['ok' => true, 'unsent' => 1]);

        Tenancy::as($org, function () use ($app, $event) {
            $this->assertSame('2', $app->fresh()->table_numbers);
            $this->assertTrue(FloorPlan::where('event_id', $event->id)->firstOrFail()->is_public);
        });

        // Public map shows the vendor; the event page links to it.
        $this->get($this->site($org, "/events/{$event->slug}/map"))->assertOk()->assertSee('Cardboard Kings');
        $this->get($this->site($org, "/events/{$event->slug}"))->assertSee('Floor map');

        // Emailing assignments records the numbers sent and logs the email.
        $this->post($this->site($org, "/admin/events/{$event->slug}/floor-plan/notify"), ['which' => 'changed'])->assertSessionHas('status');
        Tenancy::as($org, function () use ($app) {
            $this->assertSame('2', $app->fresh()->table_notified);
            $this->assertSame(1, EmailLog::where('kind', 'tables')->count());
        });
    }

    public function test_floor_plan_rejects_duplicate_table_numbers(): void
    {
        $org = $this->organizer('nutmeg');
        $event = $this->event($org);
        $type = Tenancy::as($org, fn () => $event->tableTypes()->first()->id);
        $this->actingAs($this->staff($org));

        $payload = ['width' => 40, 'depth' => 20, 'elements' => [$this->table('a', '5', $type, 2), $this->table('b', '5', $type, 10)]];
        $this->post($this->site($org, "/admin/events/{$event->slug}/floor-plan"), ['payload' => json_encode($payload)])
            ->assertStatus(422)->assertJson(['ok' => false]);
    }

    public function test_unpublished_floor_map_is_hidden_from_the_public(): void
    {
        $org = $this->organizer('nutmeg');
        $event = $this->event($org);
        Tenancy::as($org, fn () => FloorPlan::create(['event_id' => $event->id, 'elements' => [], 'is_public' => false]));
        $this->get($this->site($org, "/events/{$event->slug}/map"))->assertNotFound();
    }

    public function test_another_organizers_floor_plan_and_email_log_are_out_of_reach(): void
    {
        $a = $this->organizer('alpha');
        $b = $this->organizer('beta');
        $this->event($a, ['title' => 'Alpha Show']);
        $betaEvent = $this->event($b, ['title' => 'Beta Fair']);
        $this->confirmedVendor($b, $betaEvent, 'secret@beta.test', 'Beta Secret Vendor');
        $this->actingAs($this->staff($a));

        $this->get($this->site($a, '/admin/events/beta-fair/floor-plan'))->assertNotFound();
        $this->post($this->site($a, '/admin/events/beta-fair/floor-plan'), ['payload' => '{}'])->assertNotFound();
        $this->get($this->site($a, '/admin/events/beta-fair/message'))->assertNotFound();
        $this->get($this->site($a, '/admin/emails'))->assertOk()->assertDontSee('secret@beta.test');
        $this->get($this->site($a, '/admin/financials'))->assertOk()->assertSee('Alpha Show')->assertDontSee('Beta Fair');
    }

    public function test_duplicate_copies_setup_but_not_vendors(): void
    {
        $org = $this->organizer('nutmeg');
        $event = $this->event($org, ['title' => 'Monthly Show']);
        $this->confirmedVendor($org, $event);
        $type = Tenancy::as($org, fn () => $event->tableTypes()->first()->id);
        Tenancy::as($org, fn () => FloorPlan::create(['event_id' => $event->id, 'elements' => [$this->table('a', '1', $type, 2)], 'is_public' => true]));
        $this->actingAs($this->staff($org));

        $this->post($this->site($org, "/admin/events/{$event->slug}/duplicate"), ['starts_on' => now()->addMonths(2)->toDateString()])
            ->assertRedirect();

        Tenancy::as($org, function () use ($event) {
            $copy = Event::where('copied_from_id', $event->id)->firstOrFail();
            $this->assertSame('draft', $copy->status);
            $this->assertSame(1, $copy->tableTypes()->count());
            $this->assertSame(1, $copy->ticketTypes()->count());
            $this->assertSame(0, $copy->applications()->count());
            $plan = $copy->floorPlan;
            $this->assertNotNull($plan);
            $this->assertFalse($plan->is_public);
            $this->assertSame($copy->tableTypes()->first()->id, $plan->elements[0]['type']); // remapped to the copy's types
        });
    }

    public function test_cover_photo_and_gallery_uploads(): void
    {
        $org = $this->organizer('nutmeg');
        $event = $this->event($org);
        $this->actingAs($this->staff($org));
        $data = Tenancy::as($org, fn () => $event->only(['type', 'title', 'status', 'vendor_payment_mode', 'payment_window_days']));

        $this->put($this->site($org, "/admin/events/{$event->slug}"), $data + [
            'starts_on' => $event->starts_on->toDateString(),
            'cover' => UploadedFile::fake()->image('cover.jpg', 2400, 1200),
            'amenities' => ['free_parking', 'food'],
            'faq' => [['q' => 'Parking?', 'a' => 'Free lot.'], ['q' => '', 'a' => '']],
            'pin_moved' => '1', 'lat' => '41.6', 'lng' => '-73.1',
        ])->assertRedirect();
        $this->post($this->site($org, "/admin/events/{$event->slug}/photos"), ['photos' => [UploadedFile::fake()->image('a.jpg', 800, 600)]]);

        Tenancy::as($org, function () use ($event) {
            $e = $event->fresh();
            $this->assertNotSame('', $e->cover_path);
            $this->assertFileExists(Uploads::dir().'/'.$e->cover_path);
            $this->assertLessThanOrEqual(1800, getimagesize(Uploads::dir().'/'.$e->cover_path)[0]); // resized
            $this->assertSame(['free_parking', 'food'], $e->amenities);
            $this->assertCount(1, $e->faq);
            $this->assertEqualsWithDelta(41.6, $e->lat, 0.0001);
            $this->assertSame(1, $e->photos()->count());
            Uploads::delete($e->cover_path);
            foreach ($e->photos as $p) {
                Uploads::delete($p->path);
            }
        });

        $this->get($this->site($org, "/events/{$event->slug}"))->assertSee('Free parking')->assertSee('Parking?');
    }

    public function test_non_image_uploads_are_refused(): void
    {
        $org = $this->organizer('nutmeg');
        $event = $this->event($org);
        $this->actingAs($this->staff($org));
        $this->post($this->site($org, "/admin/events/{$event->slug}/photos"), ['photos' => [UploadedFile::fake()->createWithContent('x.php', '<?php echo 1;')]])
            ->assertSessionHas('error');
        Tenancy::as($org, fn () => $this->assertSame(0, $event->photos()->count()));
    }

    public function test_message_vendors_emails_each_chosen_group(): void
    {
        $org = $this->organizer('nutmeg');
        $event = $this->event($org);
        $this->confirmedVendor($org, $event, 'one@example.com', 'One');
        $this->confirmedVendor($org, $event, 'two@example.com', 'Two');
        $this->actingAs($this->staff($org));

        $this->post($this->site($org, "/admin/events/{$event->slug}/message"), [
            'groups' => ['paid'], 'subject' => 'Load-in at 8', 'body' => 'Use the side door.',
        ])->assertSessionHas('status', 'Sent to 2 vendors.');
        Tenancy::as($org, fn () => $this->assertSame(2, EmailLog::where('kind', 'message')->where('subject', 'Load-in at 8')->count()));
        $this->get($this->site($org, '/admin/emails?kind=message'))->assertSee('Load-in at 8')->assertSee('Use the side door.');
    }

    public function test_share_image_and_calendar_file(): void
    {
        $org = $this->organizer('nutmeg');
        $event = $this->event($org, ['title' => 'Holiday Show']);
        $this->get($this->site($org, "/events/{$event->slug}/share.png"))->assertOk()->assertHeader('Content-Type', 'image/png');
        $this->get($this->site($org, "/events/{$event->slug}/calendar.ics"))->assertOk()
            ->assertHeader('Content-Type', 'text/calendar; charset=utf-8')->assertSee('SUMMARY:Holiday Show', false);
        $this->get($this->site($org, "/events/{$event->slug}"))->assertSee('og:image', false);
    }

    public function test_directory_shows_listings_and_takes_claims(): void
    {
        $l = Listing::create(['type' => 'craft_fair', 'title' => 'Church Bazaar', 'starts_on' => now()->addWeeks(2)->toDateString(), 'town' => 'Woodbury']);
        Listing::create(['type' => 'craft_fair', 'title' => 'Hidden Fair', 'starts_on' => now()->addWeeks(2)->toDateString(), 'status' => 'hidden']);

        $this->get($this->directory('/'))->assertSee('Church Bazaar')->assertDontSee('Hidden Fair');
        $this->get($this->directory('/?type=craft_fair'))->assertSee('Church Bazaar');
        $this->get($this->directory('/?type=card_show'))->assertDontSee('Church Bazaar');
        $this->get($this->directory('/?tickets=1'))->assertDontSee('Church Bazaar'); // listings don't sell tickets

        $this->post($this->directory("/listings/{$l->id}/claim"), ['name' => 'Pat', 'email' => 'pat@example.com', 'role' => 'Chair'])
            ->assertSessionHas('status');
        $this->assertSame(1, ListingClaim::count());
    }

    public function test_organizer_sign_up_and_platform_activation(): void
    {
        $this->post($this->directory('/start'), ['name' => 'Shoreline Crafts', 'slug' => 'admin', 'owner_name' => 'Kim',
            'email' => 'kim@example.com', 'password' => 'longpassword', 'agree' => '1'])->assertSessionHasErrors('slug');
        $this->organizer('taken');
        $this->post($this->directory('/start'), ['name' => 'X', 'slug' => 'taken', 'owner_name' => 'Kim',
            'email' => 'kim@example.com', 'password' => 'longpassword', 'agree' => '1'])->assertSessionHasErrors('slug');

        $this->post($this->directory('/start'), ['name' => 'Shoreline Crafts', 'slug' => 'Shoreline', 'owner_name' => 'Kim',
            'email' => 'kim@example.com', 'password' => 'longpassword', 'agree' => '1'])->assertRedirect($this->directory('/start/welcome'));
        $org = Organizer::where('slug', 'shoreline')->firstOrFail();
        $this->assertSame('pending', $org->status);
        Tenancy::as($org, fn () => $this->assertSame(1, User::where('email', 'kim@example.com')->count()));

        // The new owner can sign in on their own subdomain.
        $this->post($this->site($org, '/admin/login'), ['email' => 'kim@example.com', 'password' => 'longpassword'])->assertRedirect();
        $this->assertAuthenticated();

        // Platform admin needs its own sign-in.
        $this->get($this->directory('/platform'))->assertRedirect($this->directory('/platform/login'));
        $admin = PlatformAdmin::create(['name' => 'P', 'email' => 'p@example.com', 'password' => 'password']);
        $this->actingAs($admin, 'platform');
        $this->get($this->directory('/platform'))->assertOk()->assertSee('Shoreline Crafts');
        $this->post($this->directory("/platform/organizers/{$org->id}/activate"))->assertSessionHas('status');
        $this->assertSame('active', $org->fresh()->status);
    }

    public function test_organizer_staff_cannot_use_platform_admin(): void
    {
        $org = $this->organizer('nutmeg');
        $this->actingAs($this->staff($org));
        $this->get($this->directory('/platform'))->assertRedirect($this->directory('/platform/login'));
    }
}
