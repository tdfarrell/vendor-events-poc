<?php

return [

    // Working name until branding is settled. Change PLATFORM_NAME in .env.
    'name' => env('PLATFORM_NAME', 'Vendor Events'),

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

    // In local mode, show vendor sign-in links on screen (no mail server needed).
    'show_login_links' => env('APP_ENV', 'production') === 'local',
];
