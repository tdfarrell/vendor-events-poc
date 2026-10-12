<?php

namespace Database\Seeders;

use App\Models\DoorSale;
use App\Models\Event;
use App\Models\Organizer;
use App\Models\User;
use App\Models\Vendor;
use App\Services\Bookings;
use App\Services\Ticketing;
use App\Tenancy\Tenancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Demo data: three organizers, a shared pool of vendors who book with more
 * than one of them, and applications in every status.
 *
 * Organizer logins (password: "password"):
 *   owner@nutmeg.test       → nutmeg.{APP_DOMAIN}/admin
 *   owner@quarryhill.test   → quarryhill.{APP_DOMAIN}/admin
 *   owner@valleyflea.test   → valleyflea.{APP_DOMAIN}/admin
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        Mail::fake(); // don't fill the log with demo emails

        $vendors = $this->vendors();
        $bookings = app(Bookings::class);
        $ticketing = app(Ticketing::class);

        // ---------- Nutmeg Card Co. ----------
        $nutmeg = $this->organizer('Nutmeg Card Co.', 'nutmeg', '#1f4e79', 'Card shows in the Naugatuck Valley', 'Watertown');
        Tenancy::as($nutmeg, function () use ($vendors, $bookings, $ticketing) {
            $this->owner('Tim', 'owner@nutmeg.test');
            $dec = $this->event('card_show', [
                'title' => 'Nutmeg Card Show: Holiday Edition',
                'starts_on' => '2026-12-12', 'hours' => '10 AM – 3 PM', 'vendor_setup' => '8 – 9:30 AM',
                'venue_name' => 'Watertown Elks Lodge', 'venue_address' => '35 Wooster St', 'town' => 'Watertown',
                'description' => "Sports cards, Pokémon and TCG, sealed wax and graded slabs. 40 tables, free parking, food on site.\n\nKids 12 and under free.",
                'admission_note' => '$5 at the door · kids free', 'vendor_payment_mode' => 'review',
                'vendor_terms' => "Tables are non-refundable within 7 days of the show.\nSetup must be complete by 9:45 AM.",
            ]);
            $six = $dec->tableTypes()->first();
            $eight = $dec->tableTypes()->skip(1)->first();
            $this->paid($bookings, $dec, $six, $vendors['cardboard'], 2, 'online', '12, 13');
            $this->paid($bookings, $dec, $six, $vendors['pokepals'], 1, 'online', '7');
            $this->paid($bookings, $dec, $eight, $vendors['slabcity'], 1, 'cash', '1');
            $this->paid($bookings, $dec, $six, $vendors['vintagewax'], 1, 'online', '22');
            $this->paidAwaitingReview($bookings, $dec, $six, $vendors['tcgtrader']);
            $this->apply($bookings, $dec, $six, $vendors['rookiecorner'], 'incomplete');   // started checkout, never paid
            $bookings->addWalkIn($dec, $six, ['business_name' => "Gary's Cards", 'contact_name' => 'Gary', 'phone' => '203-555-0148',
                'quantity' => 1, 'table_numbers' => '30'], 'cash');

            [$order] = $ticketing->createOrder($dec, 'Jamie Rivera', 'jamie@example.com', [$dec->ticketTypes()->first()->id => 2]);
            $ticketing->fulfill($order, 'online', 'test_seed1');
            [$order] = $ticketing->createOrder($dec, 'Pat Chen', 'pat@example.com', [$dec->ticketTypes()->first()->id => 1]);
            $ticketing->fulfill($order, 'online', 'test_seed2');

            $nov = $this->event('card_show', [
                'title' => 'Nutmeg Card Show: November',
                'starts_on' => '2026-11-14', 'hours' => '10 AM – 2 PM', 'venue_name' => 'Watertown Elks Lodge',
                'venue_address' => '35 Wooster St', 'town' => 'Watertown', 'vendor_payment_mode' => 'instant',
            ]);
            $this->paid($bookings, $nov, $nov->tableTypes()->first(), $vendors['cardboard'], 1, 'online', '4');
        });

        // ---------- Quarry Hill Craft Fairs (fictional) ----------
        $quarry = $this->organizer('Quarry Hill Craft Fairs', 'quarryhill', '#8a3b2a', 'Handmade fairs since 2009', 'Middlebury');
        Tenancy::as($quarry, function () use ($vendors, $bookings) {
            $this->owner('Dana', 'owner@quarryhill.test');
            $fair = $this->event('craft_fair', [
                'title' => 'Quarry Hill Holiday Craft Fair',
                'starts_on' => '2026-12-05', 'hours' => '9 AM – 4 PM', 'vendor_setup' => '7 – 8:45 AM',
                'venue_name' => 'Shepardson Community Center', 'venue_address' => '1173 Middlebury Rd', 'town' => 'Middlebury',
                'description' => 'Over 60 local makers under one roof, just in time for holiday shopping.',
                'vendor_payment_mode' => 'approve_first', 'tickets_on_sale' => false,
            ]);
            $booth = $fair->tableTypes()->first();
            $table = $fair->tableTypes()->skip(1)->first();
            $this->paid($bookings, $fair, $booth, $vendors['birchwick'], 1, 'online', 'B4');
            $this->paid($bookings, $fair, $booth, $vendors['knotty'], 1, 'online', 'B9');
            $this->paid($bookings, $fair, $table, $vendors['pokepals'], 1, 'other', 'T2');   // crosses over from card shows
            $this->apply($bookings, $fair, $booth, $vendors['clayhouse'], 'pending');
            $this->apply($bookings, $fair, $table, $vendors['saltmarsh'], 'approved');
            $this->apply($bookings, $fair, $booth, $vendors['ironleaf'], 'waitlisted');

            $this->event('craft_fair', [
                'title' => 'Spring Makers Market', 'starts_on' => '2027-04-17', 'hours' => '10 AM – 3 PM',
                'venue_name' => 'Middlebury Town Green', 'town' => 'Middlebury', 'status' => 'draft',
            ]);
        });

        // ---------- Valley Flea & Collectibles (fictional) ----------
        $flea = $this->organizer('Valley Flea & Collectibles', 'valleyflea', '#d4a017', 'Outdoor flea markets, first Sundays', 'Naugatuck');
        Tenancy::as($flea, function () use ($vendors, $bookings, $ticketing) {
            $this->owner('Lou', 'owner@valleyflea.test');
            $market = $this->event('flea_market', [
                'title' => 'First Sunday Flea: November',
                'starts_on' => '2026-11-01', 'hours' => '7 AM – 1 PM', 'vendor_setup' => '5:30 AM',
                'venue_name' => 'Linden Park lot', 'town' => 'Naugatuck',
                'description' => 'Antiques, vintage, records, tools and the odd treasure. Early buyers in at 7, everyone else at 8.',
                'admission_note' => 'Free after 8 AM', 'vendor_payment_mode' => 'instant',
            ]);
            $space = $market->tableTypes()->first();
            $this->paid($bookings, $market, $space, $vendors['vintagewax'], 2, 'online', 'A3, A4');
            $this->paid($bookings, $market, $space, $vendors['slabcity'], 1, 'cash', 'C1');
            $this->paid($bookings, $market, $space, $vendors['ironleaf'], 1, 'online', 'B7');
            [$order] = $ticketing->createOrder($market, 'Morgan Lee', 'morgan@example.com', [$market->ticketTypes()->first()->id => 1]);
            $ticketing->fulfill($order, 'online', 'test_seed3');
            DoorSale::create(['event_id' => $market->id, 'kind' => 'sale', 'label' => 'Early buyer', 'quantity' => 3,
                'amount_cents' => 3000, 'method' => 'cash', 'ticket_type_id' => $market->ticketTypes()->first()->id]);

            $this->event('flea_market', [
                'title' => 'First Sunday Flea: December', 'starts_on' => '2026-12-06', 'hours' => '7 AM – 1 PM',
                'venue_name' => 'Linden Park lot', 'town' => 'Naugatuck', 'vendor_payment_mode' => 'instant',
            ]);
        });

        Tenancy::forget();
    }

    private function organizer(string $name, string $slug, string $color, string $tagline, string $town): Organizer
    {
        return Organizer::create([
            'name' => $name, 'slug' => $slug, 'brand_color' => $color, 'tagline' => $tagline, 'town' => $town,
            'contact_email' => 'hello@'.$slug.'.test',
        ]);
    }

    private function owner(string $name, string $email): void
    {
        User::create(['name' => $name, 'email' => $email, 'password' => 'password', 'role' => 'owner']);
    }

    private function event(string $type, array $data): Event
    {
        $template = config("event_types.$type");
        $event = new Event($data + [
            'type' => $type,
            'status' => 'published',
            'applications_open' => true,
            'tickets_on_sale' => ! empty($template['ticket_types']),
            'categories' => implode(', ', $template['categories']),
        ]);
        $event->type = $type;
        $event->slug = Event::uniqueSlug($data['title']);
        $event->save();
        $event->ensureCheckinToken();
        foreach ($template['space_types'] as $i => $t) {
            $event->tableTypes()->create($t + ['sort_order' => $i]);
        }
        foreach ($template['ticket_types'] as $i => $t) {
            $event->ticketTypes()->create($t + ['sort_order' => $i]);
        }

        return $event;
    }

    private function vendorData(Vendor $v, int $qty = 1): array
    {
        return ['email' => $v->email, 'business_name' => $v->business_name, 'contact_name' => $v->contact_name,
            'phone' => $v->phone, 'categories' => $v->categories, 'quantity' => $qty];
    }

    private function apply(Bookings $b, Event $event, $tt, Vendor $v, string $endStatus, int $qty = 1)
    {
        [$app] = $b->apply($event, $tt, $this->vendorData($v, $qty) + ['inventory_description' => $v->description]);
        if ($endStatus === 'approved') {
            $b->approve($app);
        } elseif ($endStatus === 'waitlisted') {
            $b->setStatus($app, 'waitlisted');
        } elseif ($endStatus === 'incomplete') {
            $b->markCheckoutIncomplete($app);
        }

        return $app->fresh();
    }

    private function paid(Bookings $b, Event $event, $tt, Vendor $v, int $qty, string $method, string $tables): void
    {
        [$app] = $b->apply($event, $tt, $this->vendorData($v, $qty));
        if ($event->vendor_payment_mode === 'approve_first' || $method !== 'online') {
            if ($app->status === 'checkout') {
                $b->markCheckoutIncomplete($app);   // paid at the table instead of online
                $app->refresh();
            }
            if ($app->status !== 'approved') {
                $b->approve($app);
            }
            $b->markPaid($app->fresh(), $method);
        } else {
            $b->markPaid($app, 'online', 'test_seed');
            if ($app->fresh()->status === 'pending') {
                $b->approve($app->fresh());   // review mode: approve the paid application
            }
        }
        $app->fresh()->update(['table_numbers' => $tables, 'paid_at' => Carbon::now()->subDays(rand(2, 20))]);
    }

    private function paidAwaitingReview(Bookings $b, Event $event, $tt, Vendor $v): void
    {
        [$app] = $b->apply($event, $tt, $this->vendorData($v));
        $b->markPaid($app, 'online', 'test_seed');
    }

    /** A shared pool: several of these book with more than one organizer. */
    private function vendors(): array
    {
        $rows = [
            'cardboard' => ['Cardboard Kings', 'Mike Dwyer', 'Waterbury', 'Sports cards, Graded cards', 'Vintage baseball and modern football. Always buying collections.', '@cardboardkingsct'],
            'pokepals' => ['PokéPals CT', 'Ashley Ruiz', 'Naugatuck', 'Pokémon, TCG, Sealed product', 'Singles, sealed and grab bags. Family-run.', '@pokepalsct'],
            'slabcity' => ['Slab City Collectibles', 'Ray Okafor', 'Torrington', 'Graded cards, Memorabilia', 'PSA and BGS slabs, signed memorabilia.', ''],
            'vintagewax' => ['Vintage Wax & Vinyl', 'Sue Patel', 'Thomaston', 'Sealed product, Records, Vintage', '80s and 90s wax boxes, and a crate or two of records.', '@vintagewaxvinyl'],
            'tcgtrader' => ['The TCG Trader', 'Jordan Blake', 'Southbury', 'TCG, Pokémon', 'Magic, Lorcana, One Piece and Pokémon singles.', ''],
            'rookiecorner' => ['Rookie Corner', 'Ben Alvarez', 'Prospect', 'Sports cards', 'Rookie cards and affordable dime boxes for new collectors.', ''],
            'birchwick' => ['Birch & Wick Candle Co.', 'Hannah Moore', 'Woodbury', 'Candles and soap, Home decor', 'Hand-poured soy candles in small batches.', '@birchandwick'],
            'knotty' => ['Knotty by Nature', 'Lisa Grant', 'Middlebury', 'Knit and sewn goods', 'Hand-knit hats, scarves and baby blankets.', '@knottybynaturect'],
            'clayhouse' => ['Clay House Pottery', 'Erin Walsh', 'Bethlehem', 'Home decor, Art and prints', 'Wheel-thrown mugs and serving bowls.', '@clayhousepottery'],
            'saltmarsh' => ['Saltmarsh Jewelry', 'Nina Costa', 'Milford', 'Jewelry', 'Sea glass and sterling silver.', ''],
            'ironleaf' => ['Ironleaf Woodworks', 'Sam Keller', 'Oxford', 'Woodwork, Home decor, Tools', 'Cutting boards, signs and restored hand tools.', '@ironleafwood'],
        ];
        $out = [];
        foreach ($rows as $key => [$name, $contact, $town, $cats, $desc, $ig]) {
            $v = Vendor::findOrCreateByEmail($key.'@example.com');
            $v->fill(['business_name' => $name, 'contact_name' => $contact, 'town' => $town, 'categories' => $cats,
                'description' => $desc, 'instagram' => $ig, 'phone' => '203-555-0'.rand(100, 199)])->save();
            $v->assignSlug();
            $out[$key] = $v;
        }

        return $out;
    }
}
