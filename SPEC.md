# Proof of concept spec

This is a from-scratch Laravel rebuild of the Nutmeg Card Co. show app, reshaped into a platform that many organizers share. It exists to prove two things before the real build starts:

1. **Multi-tenancy works.** Several organizers run on one install, each on their own subdomain with their own branding, and none can see another's data.
2. **A statewide directory is possible.** The root domain lists every published event and every public vendor profile across all organizers.

The product name is a placeholder (`PLATFORM_NAME` in `.env`) until branding is settled.

## How organizers are separated

| Host | What it serves |
| --- | --- |
| `{APP_DOMAIN}` (e.g. `lvh.me`) | The directory: all events, all vendors, vendor sign-in and accounts |
| `{organizer}.{APP_DOMAIN}` (e.g. `nutmeg.lvh.me`) | That organizer's public site, admin, ticketing and door check-in |
| Unknown subdomain | 404 |

- `ResolveOrganizer` middleware reads the host, loads the organizer and stores it in `App\Tenancy\Tenancy`.
- Every organizer-owned model uses the `BelongsToOrganizer` trait. It adds a global scope (`organizer_id = current organizer`) and fills `organizer_id` on create.
- **The scope fails closed.** With no organizer set, scoped queries return nothing. Code that legitimately reads across organizers (only the directory) must say so with `Tenancy::acrossOrganizers(fn () => ...)`.
- Organizer staff can only sign in to their own subdomain; the admin middleware rejects a user whose `organizer_id` doesn't match the host.
- Tests in `tests/Feature/DataIsolationTest.php` try to reach another organizer's events, applications, orders, check-in links and admin pages, and must all fail.

## Data model

Organizer-owned (scoped): `events`, `table_types`, `vendor_applications`, `ticket_types`, `ticket_orders`, `tickets`, `door_sales`, `users` (staff).

Platform-wide: `organizers`, `vendors`, `vendor_login_tokens`.

**Vendors are one account across the platform** (the old app had one vendor record per organizer). A vendor signs in once and sees bookings with every organizer. An organizer sees a vendor's contact details only through applications to its own events, which is how the data policy ("each organizer's vendors and buyers belong to them") is enforced in code.

Branding lives on `organizers`: name, tagline, contact email, email "from" name, brand color. It replaces `config.php`.

## Event types

`config/event_types.php` holds a template per type: label, what a space is called (table, booth, stall, spot), default vendor categories, default space types and whether admission is usually free.

| Type | Space | Notes |
| --- | --- | --- |
| Card show | Table | Same as the Nutmeg baseline |
| Craft / holiday fair | Booth or table | |
| Flea market | Space | |
| Comic and toy show | Table | |
| Farmers market | Stall | Free admission by default |
| Food festival | Truck or tent spot | |
| Charity / community event | Table | |

Creating an event from a type fills in its space types and categories, which the organizer can then edit.

## Ported from the Nutmeg app

- **Events** with status (draft, published, archived), date, hours, venue, town, vendor setup time, description, admission note.
- **Table types** with price, quantity and per-vendor maximum.
- **Vendor applications** with the same three payment modes: pay then review (declines refunded), pay and confirm instantly, approve first then pay by link. Same inventory rule: a space is taken when paid, in checkout (35-minute hold), or approved and inside its payment window. Waitlist, decline, cancel, table numbers.
- **Walk-in and cash vendors**: the organizer adds a vendor and marks them paid in cash in one form.
- **Tickets** with ticket types, capacity, order holds, QR codes and comps.
- **Door check-in** by private link: look up a ticket code, check in vendors, record door sales by cash or card, count free kids, live totals.
- **Per-event finances** and a vendor CSV export.
- **Passwordless vendor sign-in** by emailed link.

## New for the platform

- Subdomain per organizer, branding in the database, staff accounts with owner and staff roles.
- Event-type templates.
- Platform fees recorded on every online payment: 5% of table fees and $0.75 per online ticket (added to the buyer's total). Set in `config/platform.php`.
- Directory: events by type, town and date; vendor profiles with "where I'll be next".
- Event pages carry Open Graph tags so a link pasted into Facebook shows a proper preview.

## Payments in the POC

Payments go through a `PaymentGateway` interface. The POC ships with `FakeGateway`, a built-in "test checkout" page that succeeds or cancels on a button press, so every flow can be tested with no Stripe account. Stripe Connect Express is the next gateway to write; the fee fields are already recorded the way Connect needs them (`amount_cents`, `platform_fee_cents`, payment reference).

## Deferred (not in this POC)

Floor plan editor and public map, raffles, event photo galleries, vendor photo uploads, camera QR scanning at the door (codes are typed or pasted for now), refunds through a real processor, custom domains, organizer self-serve sign-up, the JSON feed.

## Local development

- `APP_DOMAIN=lvh.me` (any `*.lvh.me` name resolves to your own machine).
- `php artisan serve --host=0.0.0.0 --port=8000`, then open `http://lvh.me:8000` and `http://nutmeg.lvh.me:8000`.
- Emails go to `storage/logs/laravel.log`. In local mode the vendor sign-in page also shows the link on screen.

## Round two

Working name **Bonfire**. Added: Bonfire design and branding, the directory landing page with category tiles and a map, generated posters and share images, richer event pages, a sectioned event editor with cover photo, gallery, map pin, amenities and FAQ, duplicate event, the floor plan builder and public map, financials across events, vendor messaging, an email log, camera QR scanning, organizer sign-up, off-platform listings with claims, and a platform admin.

New organizer-scoped tables: `event_photos`, `floor_plans`, `email_log`. Platform-wide: `vendor_photos`, `listings`, `listing_claims`, `platform_admins`. The email log is organizer-scoped like everything else, so an organizer only sees email sent in its name.

Organizer sign-up creates the organizer as **pending**: on shared hosting each subdomain still needs a cPanel subdomain, a DNS record and a certificate, so someone marks it live in platform admin after setting those up.

Still deferred: real payments (Stripe Connect), sending email (the demo logs it), custom domains, raffles, and vendor document uploads.

## Venues

Halls, posts, churches, schools and clubs list rooms to rent and take rental requests. Stage 1 of three: listings and request forms. Availability calendars and online rentals with deposits come later.

| Table | Owned by | What |
| --- | --- | --- |
| `venues` | an organizer, or no one (an unclaimed listing) | Name, kind, address, about, house rules, parking, the email requests go to, cover photo, status (listed, active, hidden) |
| `venue_rooms` | its venue | Seated and standing capacity, price range and unit, amenities |
| `venue_inquiries` | its venue | Occasion, date (or flexible), time, guests, room, budget, message, status (new, replied, booked, declined, closed) |
| `venue_claims` | its venue | "I manage this venue" requests for platform admin |

`events.venue_id` links an event to a listed venue so it shows on the venue's page.

**Why venues aren't organizer-scoped.** Unclaimed venues belong to no organizer, so `venues` can't use the `BelongsToOrganizer` scope. Instead every admin lookup goes through `Venue::ownedByCurrent()`, which fails closed like the scope does (no organizer set means no rows), and rooms and requests are reached only through a venue that passes it. `VenueTest` tries to read and change another organizer's venue, rooms and requests, and an unclaimed venue's requests, and all of it must 404.

**Requests.** A request emails the venue (in the organizer's name, replies going to the renter) and sends the renter a copy. For an unclaimed venue the email goes to its public address with a "claim your free listing" link, which is how venues find out about the platform. Replies from admin go to the renter and land in the organizer's email log.

**Claims.** Platform admin approves a claim and either hands the venue to an existing organizer or emails a sign-up link. A venue account is an ordinary organizer account, so it can run its own events too.
