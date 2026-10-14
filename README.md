# Bonfire (working name): proof of concept

A from-scratch Laravel rebuild of the Nutmeg Card Co. show app, reshaped into one platform shared by many Connecticut event organizers. See [SPEC.md](SPEC.md) for what it proves, how organizers are kept apart, and what's deferred.

**What's in it**

- Organizers on their own subdomains (`nutmeg.lvh.me`), with name, tagline, brand color and contact details stored in the database.
- A statewide directory on the root domain (`lvh.me`): upcoming events by type, town and date, and vendor profiles showing where each vendor will be next.
- Event-type templates: card show, craft fair, flea market, comic and toy show, farmers market, food festival, charity event.
- The Nutmeg flows, rebuilt: vendor applications with all three payment modes, waitlists, walk-in and cash vendors, ticketing with QR codes and comps, phone-friendly door check-in with cash and card sales, per-event money summary, vendor CSV.
- One vendor account across every organizer, with passwordless sign-in.
- Platform fees recorded on every online payment (5% of table fees, $0.75 per ticket).
- A test checkout page standing in for Stripe, so every flow works without a payment account.
- 50 automated tests, including the ones that try to reach one organizer's data, venues or rental requests from another.

**Round two** added:

- Bonfire branding: flame logo, night-sky and flame-gradient design, Bricolage Grotesque and Figtree fonts (both under the SIL Open Font License, in `public/fonts`).
- A directory landing page with search, category tiles, a "this weekend" strip, a list beside a map with pins (Leaflet and OpenStreetMap), vendor spotlight, and off-platform listings that organizers can claim.
- Generated posters in each category's colors for events without a cover photo, and PNG share images for Facebook and text previews.
- Event pages with cover photo or poster, who's coming avatars, amenities, FAQ, photo gallery, location map with directions, organizer card, share buttons and add-to-calendar.
- A sectioned event editor (cover photo, gallery, map pin, amenities, FAQ), and duplicate-an-event for recurring shows.
- The Nutmeg floor plan builder and public floor map, ported, with table-number emails and layout copying.
- Financials across all events, with a by-month chart and CSV export.
- Message vendors by group, an email log of everything sent, camera QR scanning at the door, organizer sign-up, and a platform admin for organizers, listings and claims.

**Venues** added:

- A Venues directory (`/venues`): halls, posts, churches, schools, clubs, wedding venues and pavilions, with kind tiles, guest-count and amenity filters, and a map.
- Venue pages with rooms (seated and standing capacity, prices, amenities), house rules, events coming up there, and an "Ask about a date" request form instead of "call us".
- In organizer admin, a **Venue** tab: list a venue, add rooms and a cover photo, and work through rental requests (reply by email, mark replied, booked, declined or closed).
- Unclaimed venues the platform lists itself. Requests to them go to the venue's public email with a "claim your free listing" link; platform admin hands a claimed venue to an organizer account.
- Events can be linked to a listed venue, so they appear on the venue's page.

## Run it locally

Needs PHP 8.3+ with the SQLite extension, and Composer.

```sh
composer setup          # installs, creates .env, builds and seeds the database
php artisan serve --host=0.0.0.0 --port=8000
```

`lvh.me` and every `*.lvh.me` name point back to your own computer, so subdomains work without any setup.

| Open | What it is |
| --- | --- |
| http://lvh.me:8000 | The directory |
| http://nutmeg.lvh.me:8000 | Nutmeg Card Co.'s site |
| http://quarryhill.lvh.me:8000 | Quarry Hill Craft Fairs (fictional) |
| http://valleyflea.lvh.me:8000 | Valley Flea & Collectibles (fictional) |
| http://hillsmarket.lvh.me:8000 | Litchfield Hills Market (fictional) |
| http://brasscity.lvh.me:8000 | Brass City Comic & Toy (fictional) |
| http://riverside.lvh.me:8000 | Riverside Veterans Hall (fictional), a venue account |
| http://lvh.me:8000/venues | Venues to rent |
| http://nutmeg.lvh.me:8000/admin | Organizer admin |

**Organizer logins** (password `password`): `owner@nutmeg.test`, `owner@quarryhill.test`, `owner@valleyflea.test`, `owner@hillsmarket.test`, `owner@brasscity.test`, `owner@riverside.test` (the venue, with sample rental requests under Venue). Each only works on its own subdomain.

**Platform admin** (organizers, listings, claims): http://lvh.me:8000/platform with `admin@lvh.me` / `password`.

**Vendors** sign in at http://lvh.me:8000/vendor/login. Locally, the sign-in link appears on screen instead of being emailed. Try `pokepals@example.com`, who has booked with two organizers.

**Door check-in**: in admin, open an event, go to the Door tab and open the link. It's built for a phone.

Emails are written to `storage/logs/laravel.log`. To start over with fresh demo data: `php artisan migrate:fresh --seed`.

## Tests

```sh
php artisan test
```

- `DataIsolationTest`: other organizers' public pages, private links, admin records, logins and space types are all unreachable; scoped queries return nothing when no organizer is set.
- `VendorBookingTest`: the three payment modes, waitlisting, checkout holds expiring, refunds on decline, walk-ins, one vendor account across organizers.
- `TicketsAndDoorTest`: ticket fees, capacity, QR codes, scanning at the door, door sales, resetting the check-in link.
- `DirectoryTest`: only published upcoming events, filters, vendor "where I'll be next", vendor sign-in, Facebook link previews.
- `VenueTest`: venue filters, rental requests to claimed and unclaimed venues, validation, one organizer unable to reach another's venue, rooms or requests, claims, events linked to venues.

## Where things live

| Path | What |
| --- | --- |
| `app/Tenancy/` | Organizer scope, trait and the current-organizer holder |
| `app/Http/Middleware/ResolveOrganizer.php` | Subdomain → organizer |
| `app/Services/Bookings.php` | Space inventory and the vendor application lifecycle (from `inventory.php`) |
| `app/Services/Ticketing.php` | Ticket sales (from `tickets.php`) |
| `app/Services/Payments/` | Payment gateway interface and the test gateway |
| `config/platform.php` | Platform name, domain and fees |
| `routes/web.php` | Directory routes and organizer-site routes |
| `app/Services/FloorPlans.php`, `public/js/floorplan.js` | Floor plan builder and public map (from the Nutmeg app) |
| `app/Services/Finances.php` | Money per event and across events |
| `app/Support/Poster.php` | Generated posters (SVG) and share images (PNG) |
| `app/Support/Uploads.php` | Photo uploads: resized and re-encoded, stored in `public/uploads` |
| `config/event_types.php` | Event-type templates, colors and emoji |
| `config/venues.php` | Venue kinds, room amenities and the occasions people ask about |
| `app/Http/Controllers/*/VenueController.php` | Venues in the directory, in organizer admin and in platform admin |

Third-party code bundled in `public/`: Leaflet 1.9.4 (BSD-2-Clause, `public/vendor/leaflet/LICENSE`) and jsQR (Apache-2.0) for camera scanning. Map tiles come from OpenStreetMap; address lookups use OpenStreetMap's Nominatim, one request per save.

## Note on the lock file

This was built in an environment that couldn't reach Packagist, so `composer.lock` was assembled from a compatible Laravel 13 lock. `composer install` works as is. Composer may warn the lock is out of date; running `composer update` once on your machine refreshes it.
