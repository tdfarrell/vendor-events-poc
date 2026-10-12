# Putting the demo on Bluehost (echcards.com)

The upload zip has two folders:

| Folder | Where it goes | What it is |
| --- | --- | --- |
| `vendor-events-app/` | Your home folder next to `public_html`, or inside the web folder if that is all you can reach (two .htaccess rules then block it from the web) | The app, its settings and the demo database |
| `web-folder/` | Its **contents** go in echcards.com's web folder | The page entry point, styles and Apache rules |

The settings file (`vendor-events-app/.env`) is already filled in for echcards.com, and the database already holds the demo organizers, vendors and bookings.

## 1. Point the domain and subdomains at one folder

In Bluehost's cPanel, under **Domains**:

1. Make sure **echcards.com** is on the account, and note its document root (often `public_html/echcards.com` or `public_html`). That folder is the "web folder" below.
2. If an old site still lives in that folder, download a backup, then empty it.
3. Add three subdomains: **nutmeg**, **quarryhill** and **valleyflea** on echcards.com. Set each one's document root to **the same web folder** as echcards.com, not a new folder.
4. In **MultiPHP Manager**, set echcards.com and the three subdomains to **PHP 8.3 or newer** (8.5 is fine).

Every future organizer gets one more subdomain added the same way.

## 2. Upload and unpack (SSH)

Upload `echcards-deploy.zip` to your home folder with File Manager, then:

```sh
cd ~
unzip echcards-deploy.zip
# Copy the web files into echcards.com's web folder (change the path if yours differs):
cp -a ~/web-folder/. ~/public_html/echcards.com/
rm -rf ~/web-folder
# The app writes to these:
chmod -R u+rwX ~/vendor-events-app/storage ~/vendor-events-app/bootstrap/cache ~/vendor-events-app/database
```

Without SSH: extract the zip in File Manager, turn on **Settings → Show Hidden Files** (so `.htaccess` and `.env` are visible), and move the contents of `web-folder` into the web folder.

Check PHP has SQLite (it usually does):

```sh
php -m | grep -i sqlite     # should list pdo_sqlite and sqlite3
```

## 3. Turn on SSL

In cPanel, under **SSL/TLS Status** (or Bluehost's **Security → SSL**), make sure echcards.com and each of the three subdomains has a certificate. New subdomains can take a little while to get one.

Sign-in only works over **https**, so wait for the certificates before testing logins.

## 4. Try it

| Open | What it is |
| --- | --- |
| https://echcards.com | The directory |
| https://nutmeg.echcards.com | Nutmeg Card Co. |
| https://quarryhill.echcards.com | Quarry Hill Craft Fairs (fictional) |
| https://valleyflea.echcards.com | Valley Flea & Collectibles (fictional) |
| https://nutmeg.echcards.com/admin | Organizer admin (`owner@nutmeg.test` / `password`) |

Each organizer's sign-in page shows its demo login. Vendors sign in at https://echcards.com/vendor/login; the sign-in link shows on screen.

## Demo settings to know about

These are on in `.env` for the public demo:

- `PLATFORM_SHOW_LOGIN_LINKS=true`: vendor sign-in links appear on screen, so **anyone can sign in as any vendor**. Fine for fake demo data; turn it off before real vendors use the site.
- `PLATFORM_DEMO_MODE=true`: organizer sign-in pages show the demo password.
- `PLATFORM_NOINDEX=true`: asks search engines not to list the site.
- `MAIL_MAILER=log`: no email is sent yet. To send real email, create a mailbox in cPanel and switch to `smtp` with its settings.

Payments are simulated through the test checkout page.

## Resetting the demo data

```sh
cd ~/vendor-events-app
php artisan migrate:fresh --seed --force
```

If `php` on SSH is older than 8.3, use Bluehost's versioned binary instead (for example `/usr/local/bin/ea-php85` or `php85`).

## Updating to a newer version later

Ask Claude for a new zip, then replace `~/vendor-events-app` except its `.env` and `database/database.sqlite`, and copy the new `web-folder` contents over the old ones.

## If something goes wrong

- **"Setup needed: couldn't find the vendor-events-app folder"**: the app folder isn't in your home folder, or the web folder is nested deeper than usual. Move `vendor-events-app` next to `public_html`.
- **500 error**: check `~/vendor-events-app/storage/logs/laravel.log`, and that `storage` and `database` are writable.
- **A subdomain shows "There is no organizer at this address"**: the subdomain name doesn't match an organizer. Only nutmeg, quarryhill and valleyflea exist in the demo.
- **Can't stay signed in**: the page isn't on https yet, or the address is www.echcards.com before SSL covers it.
