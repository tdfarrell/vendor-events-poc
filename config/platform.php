<?php

return [

    // Working name until branding is settled. Change PLATFORM_NAME in .env.
    'name' => env('PLATFORM_NAME', 'Bonfire'),

    // Root domain. Organizers live at {slug}.{domain}; the directory lives at {domain}.
    'domain' => env('APP_DOMAIN', 'lvh.me'),

    // Scheme and port used when building links between the directory and organizer sites.
    'scheme' => env('APP_SCHEME', 'http'),
    'port' => env('APP_PUBLIC_PORT', '8000'),

    'state' => 'CT',

    'fees' => [
        // Percent of each online table/booth payment kept by the platform.
        'vendor_percent' => (float) env('PLATFORM_VENDOR_FEE_PERCENT', 5),
        // Flat fee per online ticket, added to the buyer's total.
        'ticket_cents' => (int) env('PLATFORM_TICKET_FEE_CENTS', 75),
    ],

    // Minutes a space or ticket is held while someone is in checkout.
    'checkout_hold_minutes' => 35,

    // Subdomains organizers can't take.
    'reserved_slugs' => ['www', 'admin', 'app', 'api', 'mail', 'help', 'support', 'static', 'assets'],

    // Show vendor sign-in links on screen instead of only emailing them.
    // On by default locally. DEMO ONLY: anyone could sign in as any vendor.
    'show_login_links' => (bool) env('PLATFORM_SHOW_LOGIN_LINKS', env('APP_ENV', 'production') === 'local'),

    // Ask search engines not to index the site (for public demos).
    'noindex' => (bool) env('PLATFORM_NOINDEX', false),

    // Things attendees want to know before they go, shown as chips on event pages.
    'amenities' => [
        'free_parking' => ['🅿️', 'Free parking'],
        'food' => ['🍔', 'Food on site'],
        'indoor' => ['🏠', 'Indoors'],
        'outdoor' => ['🌤️', 'Outdoors'],
        'kids_free' => ['🧒', 'Kids get in free'],
        'accessible' => ['♿', 'Wheelchair accessible'],
        'atm' => ['🏧', 'ATM on site'],
        'cards' => ['💳', 'Vendors take cards'],
        'dogs' => ['🐕', 'Dog friendly'],
        'restrooms' => ['🚻', 'Restrooms'],
    ],

    // Where claim requests and new organizer sign-ups are announced.
    'admin_email' => env('PLATFORM_ADMIN_EMAIL', ''),

    // Look up map coordinates for event addresses (OpenStreetMap). Off in tests.
    'geocode' => (bool) env('PLATFORM_GEOCODE', true),

    // Where to center the directory map before any events are on it (Connecticut).
    'map_center' => [41.60, -72.70],
    'map_zoom' => 9,

    // Show demo logins on organizer sign-in pages.
    'demo_mode' => (bool) env('PLATFORM_DEMO_MODE', false),
];
