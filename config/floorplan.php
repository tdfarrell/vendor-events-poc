<?php

/*
 * Floor plan items, ported from the Nutmeg app. kind => [label, sellable, default width ft, default depth ft].
 * Table numbers live on the map items; which vendor is at which table is stored
 * on vendor_applications.table_numbers, so emails, check-in and the CSV keep working.
 */
return [
    'kinds' => [
        'table6' => ['6 ft table', true, 6, 2.5],
        'table8' => ['8 ft table', true, 8, 2.5],
        'booth10' => ['10 × 10 booth', true, 10, 10],
        'booth1020' => ['10 × 20 booth', true, 20, 10],
        'poi' => ['Landmark', false, 8, 5],
        'door' => ['Door', false, 6, 1],
        'wall' => ['Wall', false, 10, 0.75],
        'pillar' => ['Column', false, 1.5, 1.5],
    ],
    // Statuses that hold (or may soon hold) a space, so they can be placed on the map.
    'placeable' => ['paid', 'approved', 'pending'],
    'max_elements' => 1500,
];
