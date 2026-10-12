<?php

/*
 * Templates per event type. Creating an event from a type fills in its space
 * types and categories; the organizer can change them afterward.
 *
 * space / spaces: what a sellable spot is called on this kind of event.
 * free_admission: whether attendees usually get in free.
 * emoji and colors: the generated poster and category tile when there's no cover photo.
 */

return [

    'card_show' => [
        'plural' => 'Card shows',
        'emoji' => '🃏',
        'colors' => ['#3B4BFF', '#9B5CFF'],
        'label' => 'Card show',
        'space' => 'table',
        'spaces' => 'tables',
        'free_admission' => false,
        'categories' => ['Sports cards', 'Pokémon', 'TCG', 'Sealed product', 'Graded cards', 'Memorabilia', 'Supplies'],
        'space_types' => [
            ['name' => '6 ft table', 'price_cents' => 5000, 'quantity' => 30, 'max_per_vendor' => 2],
            ['name' => '8 ft table', 'price_cents' => 6500, 'quantity' => 10, 'max_per_vendor' => 2],
        ],
        'ticket_types' => [
            ['name' => 'General admission', 'price_cents' => 500, 'quantity' => 300],
        ],
    ],

    'craft_fair' => [
        'plural' => 'Craft fairs',
        'emoji' => '🧶',
        'colors' => ['#FF6A2B', '#E8336D'],
        'label' => 'Craft or holiday fair',
        'space' => 'booth',
        'spaces' => 'booths',
        'free_admission' => true,
        'categories' => ['Jewelry', 'Home decor', 'Candles and soap', 'Knit and sewn goods', 'Woodwork', 'Art and prints', 'Baked goods', 'Holiday'],
        'space_types' => [
            ['name' => '10 × 10 booth', 'price_cents' => 7500, 'quantity' => 25, 'max_per_vendor' => 1],
            ['name' => '6 ft table', 'price_cents' => 4000, 'quantity' => 20, 'max_per_vendor' => 2],
        ],
        'ticket_types' => [],
    ],

    'flea_market' => [
        'plural' => 'Flea markets',
        'emoji' => '🏺',
        'colors' => ['#C2781A', '#7A3E1D'],
        'label' => 'Flea market',
        'space' => 'space',
        'spaces' => 'spaces',
        'free_admission' => false,
        'categories' => ['Antiques', 'Vintage', 'Collectibles', 'Furniture', 'Tools', 'Records', 'Clothing'],
        'space_types' => [
            ['name' => 'Outdoor space', 'price_cents' => 3500, 'quantity' => 60, 'max_per_vendor' => 2],
        ],
        'ticket_types' => [
            ['name' => 'Early buyer', 'price_cents' => 1000, 'quantity' => 100],
        ],
    ],

    'comic_toy_show' => [
        'plural' => 'Comic & toy shows',
        'emoji' => '🧸',
        'colors' => ['#E8336D', '#7B2FF7'],
        'label' => 'Comic and toy show',
        'space' => 'table',
        'spaces' => 'tables',
        'free_admission' => false,
        'categories' => ['Comics', 'Vintage toys', 'Action figures', 'Funko', 'Video games', 'Art'],
        'space_types' => [
            ['name' => '6 ft table', 'price_cents' => 6000, 'quantity' => 40, 'max_per_vendor' => 3],
        ],
        'ticket_types' => [
            ['name' => 'General admission', 'price_cents' => 800, 'quantity' => 400],
        ],
    ],

    'farmers_market' => [
        'plural' => 'Farmers markets',
        'emoji' => '🥕',
        'colors' => ['#2E9D5B', '#C3D42A'],
        'label' => 'Farmers market',
        'space' => 'stall',
        'spaces' => 'stalls',
        'free_admission' => true,
        'categories' => ['Produce', 'Meat and eggs', 'Dairy', 'Baked goods', 'Prepared food', 'Flowers', 'Crafts'],
        'space_types' => [
            ['name' => 'Market stall (single date)', 'price_cents' => 3000, 'quantity' => 30, 'max_per_vendor' => 1],
        ],
        'ticket_types' => [],
    ],

    'food_festival' => [
        'plural' => 'Food festivals',
        'emoji' => '🌮',
        'colors' => ['#FF9F1C', '#FF3D3D'],
        'label' => 'Food festival',
        'space' => 'spot',
        'spaces' => 'spots',
        'free_admission' => false,
        'categories' => ['Food truck', 'BBQ', 'Desserts', 'Beverages', 'International', 'Local farm'],
        'space_types' => [
            ['name' => 'Food truck spot', 'price_cents' => 15000, 'quantity' => 15, 'max_per_vendor' => 1],
            ['name' => '10 × 10 tent', 'price_cents' => 10000, 'quantity' => 15, 'max_per_vendor' => 1],
        ],
        'ticket_types' => [
            ['name' => 'Admission', 'price_cents' => 1000, 'quantity' => 1500],
        ],
    ],

    'charity_event' => [
        'plural' => 'Community events',
        'emoji' => '🎪',
        'colors' => ['#1F8FD6', '#2EC4B6'],
        'label' => 'Charity or community event',
        'space' => 'table',
        'spaces' => 'tables',
        'free_admission' => true,
        'categories' => ['Crafts', 'Food', 'Nonprofit', 'Local business', 'Kids activities'],
        'space_types' => [
            ['name' => 'Vendor table', 'price_cents' => 2500, 'quantity' => 30, 'max_per_vendor' => 1],
        ],
        'ticket_types' => [],
    ],
];
