<?php

// Venue kinds, room amenities and the kinds of events people ask about.
return [
    'kinds' => [
        'community_center' => ['label' => 'Community center', 'plural' => 'Community centers', 'emoji' => '🏛️', 'colors' => ['#1F8FD6', '#4A3BB5']],
        'veterans_hall' => ['label' => 'Veterans hall', 'plural' => 'Veterans halls', 'emoji' => '🎖️', 'colors' => ['#2B4C7E', '#1D1533']],
        'church_hall' => ['label' => 'Church hall', 'plural' => 'Church halls', 'emoji' => '⛪', 'colors' => ['#8E5BD9', '#4A3BB5']],
        'school' => ['label' => 'School', 'plural' => 'Schools', 'emoji' => '🏫', 'colors' => ['#E8A317', '#C2781A']],
        'social_club' => ['label' => 'Social club', 'plural' => 'Social clubs', 'emoji' => '🍻', 'colors' => ['#C2410C', '#7C2D12']],
        'event_hall' => ['label' => 'Event hall', 'plural' => 'Event halls', 'emoji' => '🎉', 'colors' => ['#FF6A2B', '#E8336D']],
        'wedding_venue' => ['label' => 'Wedding venue', 'plural' => 'Wedding venues', 'emoji' => '💍', 'colors' => ['#D9467A', '#8E2A5B']],
        'outdoor' => ['label' => 'Park or pavilion', 'plural' => 'Parks & pavilions', 'emoji' => '🌳', 'colors' => ['#2E7D4F', '#1F5A3A']],
    ],

    'amenities' => [
        'kitchen' => ['🍳', 'Kitchen'],
        'bar' => ['🍷', 'Bar'],
        'tables_chairs' => ['🪑', 'Tables & chairs'],
        'stage' => ['🎤', 'Stage'],
        'dance_floor' => ['💃', 'Dance floor'],
        'av' => ['📽️', 'Sound & projector'],
        'parking' => ['🅿️', 'Parking'],
        'accessible' => ['♿', 'Accessible'],
        'wifi' => ['📶', 'Wi-Fi'],
        'outdoor_space' => ['🌿', 'Outdoor space'],
    ],

    'event_kinds' => [
        'birthday' => 'Birthday party',
        'anniversary' => 'Anniversary',
        'shower' => 'Baby or bridal shower',
        'graduation' => 'Graduation party',
        'wedding' => 'Wedding or reception',
        'memorial' => 'Memorial or repast',
        'meeting' => 'Meeting or class',
        'fundraiser' => 'Fundraiser',
        'vendor_event' => 'Craft fair, show or market',
        'other' => 'Something else',
    ],

    'price_units' => ['event' => 'per event', 'hour' => 'per hour', 'day' => 'per day'],

    'inquiry_statuses' => [
        'new' => ['New', 'pending'],
        'replied' => ['Replied', 'info'],
        'booked' => ['Booked', 'ok'],
        'declined' => ['Declined', 'declined'],
        'closed' => ['Closed', ''],
    ],
];
