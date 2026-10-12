# Vendor Events (working name): proof of concept

A from-scratch Laravel rebuild of the Nutmeg Card Co. show app, reshaped into one platform shared by many Connecticut event organizers. See [SPEC.md](SPEC.md) for what it proves, how organizers are kept apart, and what's deferred.

**What's in it**

- Organizers on their own subdomains (`nutmeg.lvh.me`), with name, tagline, brand color and contact details stored in the database.
- A statewide directory on the root domain (`lvh.me`): upcoming events by type, town and date, and vendor profiles showing where each vendor will be next.
- Event-type templates: card show, craft fair, flea market, comic and toy show, farmers market, food festival, charity event.
- The Nutmeg flows, rebuilt: vendor applications with all three payment modes, waitlists, walk-in and cash vendors, ticketing with QR codes and comps, phone-friendly door check-in with cash and card sales, per-event money summary, vendor CSV.
- One vendor account across every organizer, with passwordless sign-in.
- Platform fees recorded on every online payment (5% of table fees, $0.75 per ticket).
- A test checkout page standing in for Stripe, so every flow works without a payment account.
- 30 automated tests, including 11 that try to reach one organizer's data from another.

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
| http://nutmeg.lvh.me:8000/admin | Organizer admin |

**Organizer logins** (password `password`): `owner@nutmeg.test`, `owner@quarryhill.test`, `owner@valleyflea.test`. Each only works on its own subdomain.

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

## Where things live

| Path | What |
| --- | --- |
| `app/Tenancy/` | Organizer scope, trait and the current-organizer holder |
| `app/Http/Middleware/ResolveOrganizer.php` | Subdomain → organizer |
| `app/Services/Bookings.php` | Space inventory and the vendor application lifecycle (from `inventory.php`) |
| `app/Services/Ticketing.php` | Ticket sales (from `tickets.php`) |
| `app/Services/Payments/` | Payment gateway interface and the test gateway |
| `config/event_types.php` | Event-type templates |
| `config/platform.php` | Platform name, domain and fees |
| `routes/web.php` | Directory routes and organizer-site routes |

## Note on the lock file

This was built in an environment that couldn't reach Packagist, so `composer.lock` was assembled from a compatible Laravel 13 lock. `composer install` works as is. Composer may warn the lock is out of date; running `composer update` once on your machine refreshes it.
