<?php

namespace Tests;

use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use App\Tenancy\Tenancy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\Mail;

abstract class TestCase extends BaseTestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Tenancy::forget();
        Mail::fake();
    }

    protected function tearDown(): void
    {
        Tenancy::forget();
        parent::tearDown();
    }

    protected function organizer(string $slug, array $attrs = []): Organizer
    {
        return Organizer::create($attrs + ['name' => ucfirst($slug).' Events', 'slug' => $slug, 'contact_email' => "hello@$slug.test"]);
    }

    protected function staff(Organizer $org, string $role = 'owner'): User
    {
        return Tenancy::as($org, fn () => User::create([
            'name' => 'Staff', 'email' => $role.'@'.$org->slug.'.test', 'password' => 'password', 'role' => $role,
        ]));
    }

    /** A published event with one space type (10 × $50) and one ticket type (100 × $5). */
    protected function event(Organizer $org, array $attrs = []): Event
    {
        return Tenancy::as($org, function () use ($attrs) {
            $event = new Event($attrs + [
                'type' => 'card_show', 'title' => 'Test Show', 'starts_on' => now()->addMonth()->toDateString(),
                'town' => 'Watertown', 'status' => 'published', 'applications_open' => true,
                'tickets_on_sale' => true, 'vendor_payment_mode' => 'review',
            ]);
            $event->slug = Event::uniqueSlug($event->title);
            $event->save();
            $event->ensureCheckinToken();
            $event->tableTypes()->create(['name' => '6 ft table', 'price_cents' => 5000, 'quantity' => 10, 'max_per_vendor' => 2]);
            $event->ticketTypes()->create(['name' => 'General', 'price_cents' => 500, 'quantity' => 100]);

            return $event->fresh();
        });
    }

    protected function site(Organizer $org, string $path = '/'): string
    {
        return 'http://'.$org->slug.'.lvh.me'.$path;
    }

    protected function directory(string $path = '/'): string
    {
        return 'http://lvh.me'.$path;
    }

    protected function applyData(Event $event, array $overrides = []): array
    {
        $tt = Tenancy::as($event->organizer, fn () => $event->tableTypes()->first());

        return $overrides + [
            'table_type_id' => $tt->id, 'quantity' => 1, 'business_name' => 'Cardboard Kings',
            'contact_name' => 'Mike', 'email' => 'mike@example.com', 'phone' => '203-555-0100',
        ];
    }
}
