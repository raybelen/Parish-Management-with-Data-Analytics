<?php

$regularSchedules = [
    [
        'days' => [1, 2, 3, 4, 5, 6],
        'day_label' => 'Monday – Saturday',
        'service' => 'Daily Mass',
        'church' => 'St. John Nepomucene Parish',
        'times' => [
            ['value' => '07:00', 'label' => '7:00 AM'],
        ],
    ],
    [
        'days' => [3],
        'day_label' => 'Wednesday',
        'service' => 'Daily Mass',
        'church' => 'St. John Nepomucene Parish',
        'times' => [
            ['value' => '17:00', 'label' => '5:00 PM'],
        ],
    ],
    [
        'days' => [6],
        'day_label' => 'Saturday',
        'service' => 'Anticipated Mass',
        'church' => 'Nuestra Señora de los Desamparados',
        'times' => [
            ['value' => '16:00', 'label' => '4:00 PM'],
        ],
    ],
    [
        'days' => [6],
        'day_label' => 'Saturday',
        'service' => 'Anticipated Mass',
        'church' => 'San Isidro Labrador Chapel',
        'times' => [
            ['value' => '17:00', 'label' => '5:00 PM'],
        ],
    ],
    [
        'days' => [7],
        'day_label' => 'Sunday',
        'service' => 'Sunday Mass',
        'church' => 'Santa Misericordia Chapel',
        'times' => [
            ['value' => '06:00', 'label' => '6:00 AM'],
        ],
    ],
    [
        'days' => [7],
        'day_label' => 'Sunday',
        'service' => 'Sunday Mass',
        'church' => 'St. John Nepomucene Parish',
        'times' => [
            ['value' => '07:30', 'label' => '7:30 AM'],
            ['value' => '17:00', 'label' => '5:00 PM'],
        ],
    ],
    [
        'days' => [7],
        'day_label' => 'Sunday',
        'service' => 'Sunday Mass',
        'church' => 'San Fernando Chapel',
        'times' => [
            ['value' => '09:00', 'label' => '9:00 AM'],
        ],
    ],
];

$publicMassSchedules = [];
foreach ($regularSchedules as $schedule) {
    $service = $schedule['service'];
    $publicMassSchedules[$service]['service'] = $service;
    $publicMassSchedules[$service]['featured'] = $service === 'Sunday Mass';
    $publicMassSchedules[$service]['entries'][] = [
        'church' => $schedule['church'],
        'day' => $schedule['day_label'],
        'time' => implode(' & ', array_column($schedule['times'], 'label')),
    ];
}

return [
    'website_content_cache_store' => env('WEBSITE_CONTENT_CACHE_STORE', 'file'),

    'navigation' => [
        'Home' => 'home',
        'About' => 'about',
        'Services' => 'services',
        'Announcements' => 'announcements',
        'Ministries & Organizations' => 'ministries',
        'Gallery' => 'gallery',
        'Contact' => 'contact',
    ],

    'churches' => [
        'St. John Nepomucene Parish',
        'San Isidro Labrador Chapel',
        'Nuestra Señora de los Desamparados',
        'San Fernando Chapel',
        'Santa Misericordia Chapel',
    ],

    'public_mass_schedules' => $publicMassSchedules,

    'regular_schedules' => $regularSchedules,
];
