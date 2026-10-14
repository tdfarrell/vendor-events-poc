<?php

namespace Database\Seeders;

use App\Models\DoorSale;
use App\Models\Event;
use App\Models\FloorPlan;
use App\Models\Listing;
use App\Models\Organizer;
use App\Models\PlatformAdmin;
use App\Models\User;
use App\Models\Vendor;
use App\Models\Venue;
use App\Models\VenueInquiry;
use App\Services\Bookings;
use App\Services\Ticketing;
use App\Tenancy\Tenancy;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;

/**
 * Demo data: five organizers across every kind of event, a shared pool of
 * vendors who book with more than one of them, applications in every status,
 * a laid-out floor plan, a few off-platform directory listings, and venues:
 * one run by its own account with rental requests, the rest unclaimed.
 * Everything except Nutmeg Card Co. is made up.
 *
 * Organizer logins (password: "password"), each on its own subdomain:
 *   owner@nutmeg.test, owner@quarryhill.test, owner@valleyflea.test,
 *   owner@hillsmarket.test, owner@brasscity.test, owner@riverside.test (a venue)
 * Platform admin: admin@{APP_DOMAIN} / password at {APP_DOMAIN}/platform
 */
class DatabaseSeeder extends Seeder
{
    /** Town centers, so the demo map works without looking addresses up. */
    private const TOWNS = [
        'Watertown' => [41.6062, -73.1179],
        'Middlebury' => [41.5276, -73.1276],
        'Naugatuck' => [41.4859, -73.0507],
        'Litchfield' => [41.7473, -73.1887],
        'Waterbury' => [41.5582, -73.0515],
        'Woodbury' => [41.5445, -73.2090],
        'Southbury' => [41.4815, -73.2132],
        'Cheshire' => [41.4990, -72.9007],
        'Torrington' => [41.8007, -73.1212],
        'New Milford' => [41.5770, -73.4085],
    ];

    public function run(): void
    {
        Mail::fake();
        $vendors = $this->vendors();
        $bookings = app(Bookings::class);
        $ticketing = app(Ticketing::class);
        $today = today();
        $sat = $today->isSaturday() ? $today->copy() : $today->copy()->next(Carbon::SATURDAY);
        $sun = $sat->copy()->addDay();

        PlatformAdmin::create(['name' => 'Platform admin', 'email' => 'admin@'.config('platform.domain'), 'password' => 'password']);

        // ---------- Nutmeg Card Co. ----------
        $nutmeg = $this->organizer('Nutmeg Card Co.', 'nutmeg', '#1F4E79', 'Card shows in the Naugatuck Valley', 'Watertown',
            'Monthly card shows for collectors of every age: sports, Pokémon and every TCG, sealed and slabbed. Family friendly, kids always free.');
        Tenancy::as($nutmeg, function () use ($vendors, $bookings, $ticketing) {
            $this->owner('Tim', 'owner@nutmeg.test');
            $dec = $this->event('card_show', [
                'title' => 'Nutmeg Card Show: Holiday Edition',
                'starts_on' => '2026-12-12', 'hours' => '10 AM – 3 PM', 'vendor_setup' => '8 – 9:30 AM',
                'venue_name' => 'Watertown Elks Lodge', 'venue_address' => '35 Wooster St', 'town' => 'Watertown',
                'description' => "Our biggest show of the year, just in time for holiday shopping. Forty tables of sports cards, Pokémon and every TCG, sealed wax and graded slabs.\n\nBring your binder: lots of our dealers buy and trade, too.",
                'admission_note' => '$5 at the door · kids 12 and under free', 'vendor_payment_mode' => 'review',
                'vendor_terms' => "Tables are non-refundable within 7 days of the show.\nSetup must be finished by 9:45 AM.",
                'parking_info' => 'Free lot beside the lodge; overflow on Wooster St.',
                'amenities' => ['free_parking', 'food', 'indoor', 'kids_free', 'accessible', 'atm', 'cards'],
                'faq' => [
                    ['q' => 'Can I sell or trade cards?', 'a' => 'Yes. Most dealers buy collections and trade. Bring your binder.'],
                    ['q' => 'Is there food?', 'a' => 'The lodge kitchen has hot dogs, pizza and drinks all day.'],
                    ['q' => 'Do kids need a ticket?', 'a' => 'Kids 12 and under get in free.'],
                ],
            ]);
            $six = $dec->tableTypes()->first();
            $eight = $dec->tableTypes()->skip(1)->first();
            $this->paid($bookings, $dec, $six, $vendors['cardboard'], 2, 'online', '12, 13');
            $this->paid($bookings, $dec, $six, $vendors['pokepals'], 1, 'online', '17');
            $this->paid($bookings, $dec, $eight, $vendors['slabcity'], 1, 'cash', '1');
            $this->paid($bookings, $dec, $six, $vendors['vintagewax'], 1, 'online', '22');
            $this->paid($bookings, $dec, $six, $vendors['rookiecorner'], 1, 'online', '');
            $this->paidAwaitingReview($bookings, $dec, $six, $vendors['tcgtrader']);
            $bookings->addWalkIn($dec, $six, ['business_name' => "Gary's Cards", 'contact_name' => 'Gary', 'phone' => '203-555-0148',
                'quantity' => 1, 'table_numbers' => '30'], 'cash');
            $this->floorPlan($dec, $six->id, $eight->id);

            foreach ([['Jamie Rivera', 'jamie@example.com', 2], ['Pat Chen', 'pat@example.com', 1], ['Sam Ortiz', 'sam@example.com', 4]] as $i => [$n, $e, $q]) {
                [$order] = $ticketing->createOrder($dec, $n, $e, [$dec->ticketTypes()->first()->id => $q]);
                $ticketing->fulfill($order, 'online', 'test_seed'.$i);
            }

            $nov = $this->event('card_show', [
                'title' => 'Nutmeg Card Show: November',
                'starts_on' => '2026-11-14', 'hours' => '10 AM – 2 PM', 'venue_name' => 'Watertown Elks Lodge',
                'venue_address' => '35 Wooster St', 'town' => 'Watertown', 'vendor_payment_mode' => 'instant',
                'description' => 'Our monthly show. Thirty-plus dealers, a kids trade table, and free grading drop-off.',
                'admission_note' => '$5 · kids free', 'amenities' => ['free_parking', 'indoor', 'kids_free', 'food'],
            ]);
            $this->paid($bookings, $nov, $nov->tableTypes()->first(), $vendors['cardboard'], 1, 'online', '4');
            $this->paid($bookings, $nov, $nov->tableTypes()->first(), $vendors['tcgtrader'], 1, 'online', '9');

            // A past show, so financials have history.
            $sep = $this->event('card_show', [
                'title' => 'Nutmeg Card Show: September', 'starts_on' => '2026-09-12', 'hours' => '10 AM – 2 PM',
                'venue_name' => 'Watertown Elks Lodge', 'venue_address' => '35 Wooster St', 'town' => 'Watertown', 'vendor_payment_mode' => 'instant',
            ]);
            foreach (['cardboard' => '3', 'pokepals' => '8', 'vintagewax' => '14', 'slabcity' => '2'] as $k => $t) {
                $this->paid($bookings, $sep, $sep->tableTypes()->first(), $vendors[$k], 1, $k === 'slabcity' ? 'cash' : 'online', $t);
            }
            DoorSale::create(['event_id' => $sep->id, 'kind' => 'sale', 'label' => 'General admission', 'quantity' => 142, 'amount_cents' => 71000, 'method' => 'cash']);
            DoorSale::create(['event_id' => $sep->id, 'kind' => 'sale', 'label' => 'General admission', 'quantity' => 38, 'amount_cents' => 19000, 'method' => 'card']);
            DoorSale::create(['event_id' => $sep->id, 'kind' => 'kids', 'label' => 'Free kids', 'quantity' => 47, 'amount_cents' => 0, 'method' => 'other']);
        });

        // ---------- Quarry Hill Craft Fairs ----------
        $quarry = $this->organizer('Quarry Hill Craft Fairs', 'quarryhill', '#B23A48', 'Handmade fairs since 2009', 'Middlebury',
            'Juried craft fairs featuring Connecticut makers: pottery, candles, knits, jewelry and woodwork.');
        Tenancy::as($quarry, function () use ($vendors, $bookings) {
            $this->owner('Dana', 'owner@quarryhill.test');
            $fair = $this->event('craft_fair', [
                'title' => 'Quarry Hill Holiday Craft Fair',
                'starts_on' => '2026-12-05', 'hours' => '9 AM – 4 PM', 'vendor_setup' => '7 – 8:45 AM',
                'venue_name' => 'Shepardson Community Center', 'venue_address' => '1173 Middlebury Rd', 'town' => 'Middlebury',
                'description' => "Over 60 local makers under one roof, just in time for holiday shopping. Hot cider, a bake sale for the library, and photos with Santa from 11 to 1.",
                'vendor_payment_mode' => 'approve_first', 'admission_note' => 'Free admission',
                'amenities' => ['free_parking', 'food', 'indoor', 'accessible', 'restrooms', 'cards'],
                'faq' => [['q' => 'Is it free to get in?', 'a' => 'Yes, admission is free. The bake sale benefits the library.']],
            ]);
            $booth = $fair->tableTypes()->first();
            $table = $fair->tableTypes()->skip(1)->first();
            $this->paid($bookings, $fair, $booth, $vendors['birchwick'], 1, 'online', 'B4');
            $this->paid($bookings, $fair, $booth, $vendors['knotty'], 1, 'online', 'B9');
            $this->paid($bookings, $fair, $table, $vendors['pokepals'], 1, 'other', 'T2');
            $this->paid($bookings, $fair, $booth, $vendors['saltmarsh'], 1, 'online', 'B2');
            $this->apply($bookings, $fair, $booth, $vendors['clayhouse'], 'pending');
            $this->apply($bookings, $fair, $table, $vendors['ironleaf'], 'waitlisted');

            $this->event('craft_fair', [
                'title' => 'Spring Makers Market', 'starts_on' => '2027-04-17', 'hours' => '10 AM – 3 PM',
                'venue_name' => 'Middlebury Town Green', 'town' => 'Middlebury', 'status' => 'draft',
            ]);
        });

        // ---------- Valley Flea & Collectibles ----------
        $flea = $this->organizer('Valley Flea & Collectibles', 'valleyflea', '#C2781A', 'Outdoor flea markets, first Sundays', 'Naugatuck',
            'Outdoor flea markets on the first Sunday of the month, spring through fall. Early buyers at 7.');
        Tenancy::as($flea, function () use ($vendors, $bookings, $ticketing) {
            $this->owner('Lou', 'owner@valleyflea.test');
            $market = $this->event('flea_market', [
                'title' => 'First Sunday Flea: November',
                'starts_on' => '2026-11-01', 'hours' => '7 AM – 1 PM', 'vendor_setup' => '5:30 AM',
                'venue_name' => 'Linden Park lot', 'venue_address' => 'Linden Park', 'town' => 'Naugatuck',
                'description' => 'Antiques, vintage, records, tools and the odd treasure. Early buyers in at 7, everyone else at 8.',
                'admission_note' => 'Free after 8 AM', 'vendor_payment_mode' => 'instant',
                'amenities' => ['free_parking', 'outdoor', 'dogs', 'food'],
            ]);
            $space = $market->tableTypes()->first();
            $this->paid($bookings, $market, $space, $vendors['vintagewax'], 2, 'online', 'A3, A4');
            $this->paid($bookings, $market, $space, $vendors['slabcity'], 1, 'cash', 'C1');
            $this->paid($bookings, $market, $space, $vendors['ironleaf'], 1, 'online', 'B7');
            [$order] = $ticketing->createOrder($market, 'Morgan Lee', 'morgan@example.com', [$market->ticketTypes()->first()->id => 1]);
            $ticketing->fulfill($order, 'online', 'test_seed_flea');

            $this->event('flea_market', [
                'title' => 'First Sunday Flea: December', 'starts_on' => '2026-12-06', 'hours' => '7 AM – 1 PM',
                'venue_name' => 'Linden Park lot', 'venue_address' => 'Linden Park', 'town' => 'Naugatuck', 'vendor_payment_mode' => 'instant',
                'amenities' => ['free_parking', 'outdoor', 'dogs'],
            ]);
        });

        // ---------- Litchfield Hills Farmers Market (this weekend) ----------
        $hills = $this->organizer('Litchfield Hills Market', 'hillsmarket', '#2E7D4F', 'Saturday farmers market on the green', 'Litchfield',
            'Farmers, bakers and makers from the hills, every Saturday morning through December.');
        Tenancy::as($hills, function () use ($vendors, $bookings, $sat) {
            $this->owner('Ruth', 'owner@hillsmarket.test');
            $mkt = $this->event('farmers_market', [
                'title' => 'Saturday Market on the Green',
                'starts_on' => $sat->toDateString(), 'hours' => '9 AM – 12 PM', 'vendor_setup' => '7:30 AM',
                'venue_name' => 'Litchfield Town Green', 'venue_address' => 'West St', 'town' => 'Litchfield',
                'description' => 'Apples, cider, eggs, sourdough, cut flowers and the last tomatoes of the year. Live fiddle music from 10.',
                'admission_note' => 'Free', 'vendor_payment_mode' => 'instant',
                'amenities' => ['outdoor', 'dogs', 'food', 'cards'],
            ]);
            $stall = $mkt->tableTypes()->first();
            $this->paid($bookings, $mkt, $stall, $vendors['birchwick'], 1, 'online', '5');
            $this->paid($bookings, $mkt, $stall, $vendors['hollowfarm'], 1, 'online', '1');
            $this->paid($bookings, $mkt, $stall, $vendors['risebakery'], 1, 'cash', '2');
        });

        // ---------- Brass City Comic & Toy Show (this weekend) ----------
        $brass = $this->organizer('Brass City Comic & Toy', 'brasscity', '#7B2FF7', 'Comics, toys and games in Waterbury', 'Waterbury',
            'A comic and toy show for collectors and families, three times a year.');
        Tenancy::as($brass, function () use ($vendors, $bookings, $sun) {
            $this->owner('Andre', 'owner@brasscity.test');
            $show = $this->event('comic_toy_show', [
                'title' => 'Brass City Comic & Toy Show',
                'starts_on' => $sun->toDateString(), 'hours' => '10 AM – 4 PM', 'vendor_setup' => '8 AM',
                'venue_name' => 'Waterbury Armory', 'venue_address' => '64 Field St', 'town' => 'Waterbury',
                'description' => 'Back issues, vintage toys, retro games and local artists. Costume contest at 2 PM.',
                'admission_note' => '$8 · kids under 10 free', 'vendor_payment_mode' => 'review',
                'amenities' => ['indoor', 'food', 'kids_free', 'accessible', 'atm'],
            ]);
            $t = $show->tableTypes()->first();
            $this->paid($bookings, $show, $t, $vendors['retrovault'], 2, 'online', '10, 11');
            $this->paid($bookings, $show, $t, $vendors['tcgtrader'], 1, 'online', '4');
        });

        // ---------- Venues ----------
        $this->venues($vendors, $bookings, $today);

        // ---------- Off-platform listings (sample) ----------
        foreach ([
            ['charity_event', 'St. Anne’s Holiday Bazaar', '2026-11-21', '9 AM – 2 PM', 'St. Anne Parish Hall', 'Woodbury', 'St. Anne Parish'],
            ['food_festival', 'Harvest Food Truck Rally', '2026-10-24', '11 AM – 6 PM', 'Fairgrounds', 'Southbury', 'Southbury Rotary'],
            ['craft_fair', 'Cheshire PTO Craft Fair', '2026-11-07', '10 AM – 3 PM', 'Cheshire High School', 'Cheshire', 'Cheshire PTO'],
            ['flea_market', 'Torrington Antique & Flea', '2026-10-25', '8 AM – 2 PM', 'Coe Park', 'Torrington', ''],
            ['card_show', 'New Milford Sports Card Show', '2026-11-28', '9 AM – 2 PM', 'VFW Post', 'New Milford', 'VFW Post'],
        ] as [$type, $title, $date, $hours, $venue, $town, $host]) {
            Listing::create([
                'type' => $type, 'title' => $title, 'starts_on' => $date, 'hours' => $hours, 'venue_name' => $venue, 'town' => $town,
                'host_name' => $host, 'lat' => self::TOWNS[$town][0] + 0.004, 'lng' => self::TOWNS[$town][1] - 0.003,
                'description' => 'Sample listing for the demo. Listings show events run off the platform until their organizer claims them.',
            ]);
        }

        Tenancy::forget();
    }

    /** A veterans hall with its own account and rental requests, plus unclaimed venues across the map. */
    private function venues(array $vendors, Bookings $bookings, Carbon $today): void
    {
        $river = $this->organizer('Riverside Veterans Hall', 'riverside', '#2B4C7E', 'Our hall, your party', 'Torrington',
            'A volunteer-run veterans hall on the Naugatuck River. We rent our hall, canteen and pavilion to the community, and host a holiday fair every year.');
        [$lat, $lng] = self::TOWNS['Torrington'];
        $hall = new Venue([
            'name' => 'Riverside Veterans Hall', 'kind' => 'veterans_hall', 'address' => '12 River Rd', 'town' => 'Torrington',
            'about' => "A big, bright hall with a full kitchen and a bar run by our members. Birthday parties, showers, anniversaries, repasts and fundraisers are our bread and butter.\n\nTables and chairs for 180 are included, and our members can run the bar for you.",
            'rules' => "Alcohol is served by our bar only; no outside alcohol.\nOutside caterers are welcome; the kitchen is yours for the day.\n\$150 refundable deposit holds your date.\nEvents end by 11 PM; cleanup by midnight.\nNonprofits and veterans' families get 20% off.",
            'parking_info' => 'Free lot for 80 cars, plus street parking on River Rd.',
            'contact_email' => 'hello@riverside.test', 'phone' => '860-555-0142', 'status' => 'active',
        ]);
        $hall->organizer_id = $river->id;
        $hall->slug = Venue::uniqueSlug('Riverside Veterans Hall', 'Torrington');
        [$hall->lat, $hall->lng] = [$lat + 0.006, $lng - 0.004];
        $hall->save();
        $main = $hall->rooms()->create(['name' => 'Main hall', 'seated' => 180, 'standing' => 250, 'price_from' => 450, 'price_to' => 650, 'price_unit' => 'event',
            'description' => 'Hardwood dance floor, stage with sound system, and a pass-through to the kitchen. Round tables seat 10.',
            'amenities' => ['kitchen', 'bar', 'tables_chairs', 'stage', 'dance_floor', 'av', 'parking', 'accessible'], 'sort_order' => 1]);
        $canteen = $hall->rooms()->create(['name' => 'Canteen room', 'seated' => 50, 'standing' => 70, 'price_from' => 175, 'price_unit' => 'event',
            'description' => 'A cozy room for showers, small birthdays and meetings, right beside the kitchen.',
            'amenities' => ['kitchen', 'tables_chairs', 'parking', 'accessible', 'wifi'], 'sort_order' => 2]);
        $hall->rooms()->create(['name' => 'Riverside pavilion', 'seated' => 80, 'standing' => 120, 'price_from' => 250, 'price_unit' => 'day',
            'description' => 'Covered pavilion on the lawn with picnic tables and a grill. May to October.',
            'amenities' => ['outdoor_space', 'tables_chairs', 'parking'], 'sort_order' => 3]);

        Tenancy::as($river, function () use ($hall, $main, $canteen, $vendors, $bookings, $today) {
            $this->owner('Ray', 'owner@riverside.test');
            $fair = $this->event('craft_fair', [
                'title' => 'Riverside Holiday Vendor Fair', 'starts_on' => '2026-11-28', 'hours' => '9 AM – 3 PM', 'vendor_setup' => '7:30 – 8:45 AM',
                'venue_name' => 'Riverside Veterans Hall', 'venue_address' => '12 River Rd', 'town' => 'Torrington', 'venue_id' => $hall->id,
                'description' => "Forty local crafters and small businesses in our main hall, with a pancake breakfast from the auxiliary until 11. Proceeds support the post's veterans fund.",
                'admission_note' => 'Free admission', 'vendor_payment_mode' => 'instant', 'amenities' => ['free_parking', 'food', 'indoor', 'accessible'],
            ]);
            $this->paid($bookings, $fair, $fair->tableTypes()->first(), $vendors['birchwick'], 1, 'online', 'B1');
            $this->paid($bookings, $fair, $fair->tableTypes()->first(), $vendors['saltmarsh'], 1, 'online', 'B5');

            foreach ([
                ['Maria Lopez', 'maria@example.com', 'birthday', 24, 120, 'Main hall', '6 – 11 PM', '', "My dad's 70th! Can we bring our own caterer and do you have a projector for a slideshow?", 'new', 2],
                ['Kevin & Jo Brandt', 'kbrandt@example.com', 'anniversary', 52, 90, 'Main hall', '5 – 10 PM', 'about $600', 'Our parents\' 50th. Is the bar open-bar or cash, and what does that cost?', 'replied', 6],
                ['Tasha Green', 'tasha@example.com', 'shower', 38, 40, 'Canteen room', '1 – 4 PM', '', 'Baby shower for my sister. We\'d need the kitchen for a fridge and warming trays.', 'booked', 9],
                ['St. Peter Youth Group', 'youth@example.com', 'fundraiser', 70, 150, null, 'Afternoon', '', 'Spaghetti dinner fundraiser. Do you offer a nonprofit rate?', 'new', 1],
            ] as [$name, $email, $kind, $days, $guests, $room, $time, $budget, $msg, $status, $ago]) {
                $i = new VenueInquiry([
                    'name' => $name, 'email' => $email, 'event_kind' => $kind, 'event_date' => $today->copy()->addDays($days)->toDateString(),
                    'guests' => $guests, 'time_range' => $time, 'budget' => $budget, 'message' => $msg,
                    'venue_room_id' => $room === 'Main hall' ? $main->id : ($room === 'Canteen room' ? $canteen->id : null),
                ]);
                $i->venue_id = $hall->id;
                $i->status = $status;
                $i->replied_at = $status === 'new' ? null : now()->subDays($ago - 1);
                $i->created_at = now()->subDays($ago)->subHours(3);
                $i->save();
            }
        });

        // Unclaimed venues: listed by the platform; requests go to their public email with a claim link.
        foreach ([
            ['Maple Street Community Center', 'community_center', 'Watertown', 'hall@maplestreet.example',
                'The town community center with a gym-sized hall and two meeting rooms. Popular for kids\' birthdays and club meetings.',
                [['Gym hall', 150, 220, 300, ['tables_chairs', 'stage', 'parking', 'accessible']], ['Meeting room A', 30, 40, 60, ['tables_chairs', 'av', 'wifi', 'accessible']]]],
            ['Grace Fellowship Hall', 'church_hall', 'Naugatuck', 'office@gracefellowship.example',
                'Fellowship hall in the church basement with a commercial kitchen. Repasts, showers and community suppers.',
                [['Fellowship hall', 120, 150, 250, ['kitchen', 'tables_chairs', 'parking', 'accessible']]]],
            ['Harborview Social Club', 'social_club', 'Waterbury', 'rentals@harborview.example',
                'A members\' club with a function room and full bar, open to the public for private parties.',
                [['Function room', 100, 140, 400, ['bar', 'kitchen', 'tables_chairs', 'dance_floor', 'av', 'parking']]]],
            ['Oak Hill Elementary School', 'school', 'Cheshire', 'facilities@oakhill.example',
                'Cafeteria and gym available evenings and weekends through the district facilities office. Great for PTO fairs.',
                [['Cafeteria', 200, 250, 150, ['tables_chairs', 'parking', 'accessible']], ['Gymnasium', null, 400, 200, ['parking', 'accessible']]]],
            ['The Granary at Whitfield Farm', 'wedding_venue', 'Litchfield', 'events@whitfieldfarm.example',
                'A restored 1890s granary barn with string lights and a meadow for ceremonies.',
                [['Barn', 160, 200, 3500, ['bar', 'dance_floor', 'outdoor_space', 'tables_chairs', 'parking']]]],
            ['Pomperaug Grange Hall', 'community_center', 'Woodbury', 'grange@pomperaug.example',
                'A classic 1920s grange hall with a stage and kitchen, rented to the community for parties, dances and fairs.',
                [['Hall', 90, 130, 200, ['kitchen', 'stage', 'tables_chairs', 'dance_floor', 'parking']]]],
            ['Lakeside Park Pavilion', 'outdoor', 'Southbury', 'parks@southbury.example',
                'A large covered pavilion by the lake with grills, restrooms and a playground next door.',
                [['Pavilion', 120, 160, 125, ['outdoor_space', 'tables_chairs', 'parking', 'accessible']]]],
        ] as $n => [$name, $kind, $town, $email, $about, $rooms]) {
            [$lat, $lng] = self::TOWNS[$town];
            $v = new Venue(['name' => $name, 'kind' => $kind, 'town' => $town, 'contact_email' => $email, 'about' => $about, 'status' => 'listed']);
            $v->slug = Venue::uniqueSlug($name, $town);
            [$v->lat, $v->lng] = [$lat - 0.005 + $n * 0.001, $lng + 0.006 - $n * 0.0012];
            $v->save();
            foreach ($rooms as $i => [$room, $seated, $standing, $price, $amen]) {
                $v->rooms()->create(['name' => $room, 'seated' => $seated, 'standing' => $standing, 'price_from' => $price,
                    'price_unit' => $kind === 'outdoor' ? 'day' : 'event', 'amenities' => $amen, 'sort_order' => $i]);
            }
            if ($n === 0) {
                $v->claims()->create(['name' => 'Linda Pierce', 'email' => 'linda@maplestreet.example', 'role' => 'Center director',
                    'note' => 'We get calls every week about renting the gym. Would love to point people here.']);
            }
        }
    }

    private function organizer(string $name, string $slug, string $color, string $tagline, string $town, string $about): Organizer
    {
        return Organizer::create([
            'name' => $name, 'slug' => $slug, 'brand_color' => $color, 'tagline' => $tagline, 'town' => $town,
            'contact_email' => 'hello@'.$slug.'.test', 'about' => $about, 'status' => 'active',
        ]);
    }

    private function owner(string $name, string $email): void
    {
        User::create(['name' => $name, 'email' => $email, 'password' => 'password', 'role' => 'owner']);
    }

    private function event(string $type, array $data): Event
    {
        $template = config("event_types.$type");
        [$lat, $lng] = self::TOWNS[$data['town']] ?? [null, null];
        $event = new Event($data + [
            'type' => $type,
            'status' => 'published',
            'applications_open' => true,
            'tickets_on_sale' => ! empty($template['ticket_types']),
            'categories' => implode(', ', $template['categories']),
            'amenities' => [],
            'faq' => [],
        ]);
        $event->type = $type;
        $event->slug = Event::uniqueSlug($data['title']);
        // Nudge pins a little so events in the same town don't stack exactly.
        $event->lat = $lat !== null ? $lat + (crc32($data['title']) % 9 - 4) * 0.0012 : null;
        $event->lng = $lng !== null ? $lng + (crc32(strrev($data['title'])) % 9 - 4) * 0.0012 : null;
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

    /** A hall: a front row of ten 8 ft tables (1–10), then three rows of 6 ft tables (11–40). */
    private function floorPlan(Event $event, int $sixId, int $eightId): void
    {
        $els = [];
        $n = 1;
        for ($i = 0; $i < 10; $i++) {
            $els[] = ['id' => 'e'.$n, 'kind' => 'table8', 'x' => 4 + $i * 8, 'y' => 6, 'w' => 8, 'h' => 2.5, 'rot' => 0, 'num' => (string) $n++, 'type' => $eightId];
        }
        foreach ([18, 20.5, 33] as $y) {
            for ($i = 0; $i < 10; $i++) {
                $els[] = ['id' => 'e'.$n, 'kind' => 'table6', 'x' => 10 + $i * 6, 'y' => $y, 'w' => 6, 'h' => 2.5, 'rot' => 0, 'num' => (string) $n++, 'type' => $sixId];
            }
        }
        $els[] = ['id' => 'door1', 'kind' => 'door', 'x' => 40, 'y' => 49, 'w' => 8, 'h' => 1, 'rot' => 0, 'label' => 'Entrance'];
        $els[] = ['id' => 'poi1', 'kind' => 'poi', 'x' => 34, 'y' => 42, 'w' => 10, 'h' => 4, 'rot' => 0, 'label' => 'Check-in'];
        $els[] = ['id' => 'poi2', 'kind' => 'poi', 'x' => 66, 'y' => 42, 'w' => 14, 'h' => 6, 'rot' => 0, 'label' => 'Food & drinks'];
        $els[] = ['id' => 'poi3', 'kind' => 'poi', 'x' => 4, 'y' => 42, 'w' => 12, 'h' => 6, 'rot' => 0, 'label' => 'Kids trade table'];
        $els[] = ['id' => 'p1', 'kind' => 'pillar', 'x' => 42, 'y' => 27, 'w' => 1.5, 'h' => 1.5, 'rot' => 0];
        FloorPlan::create(['event_id' => $event->id, 'width_ft' => 88, 'depth_ft' => 50, 'aisle_ft' => 5, 'elements' => $els, 'is_public' => true]);
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
        $fresh = $app->fresh();
        $fresh->update(['table_numbers' => $tables, 'table_notified' => $tables, 'paid_at' => Carbon::now()->subDays(rand(2, 20))]);
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
            'hollowfarm' => ['Hollow Brook Farm', 'Pete & Ann Lisk', 'Goshen', 'Produce, Meat and eggs', 'Pasture-raised eggs, apples and squash.', '@hollowbrookfarm'],
            'risebakery' => ['Rise & Shine Bakery', 'Carla Mendes', 'Litchfield', 'Baked goods', 'Sourdough, cider donuts and pies.', '@riseandshinebakes'],
            'retrovault' => ['Retro Vault Toys', 'Andy Kim', 'Wolcott', 'Vintage toys, Action figures, Video games', 'Loose and carded figures, NES and SNES carts.', '@retrovaulttoys'],
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
