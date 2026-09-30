<?php

return [
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
        'St. John Nepomucene Parish Church',
        'Parish Chapel',
    ],

    'regular_schedules' => [
        [
            'days' => [1, 2, 3, 4, 5],
            'day_label' => 'WEEKDAYS',
            'service' => 'Daily Mass',
            'times' => [
                ['value' => '07:00', 'label' => '7:00 AM'],
            ],
            'featured' => false,
        ],
        [
            'days' => [3],
            'day_label' => 'WEDNESDAY',
            'service' => 'Perpetual Mass',
            'times' => [
                ['value' => '16:00', 'label' => '4:00 PM'],
            ],
            'featured' => false,
        ],
        [
            'days' => [6],
            'day_label' => 'SATURDAY',
            'service' => 'Anticipated Mass',
            'times' => [
                ['value' => '16:00', 'label' => '4:00 PM'],
            ],
            'featured' => false,
        ],
        [
            'days' => [7],
            'day_label' => 'SUNDAY',
            'service' => 'Sunday Mass',
            'times' => [
                ['value' => '07:00', 'label' => '7:00 AM'],
                ['value' => '16:00', 'label' => '4:00 PM'],
            ],
            'featured' => true,
        ],
    ],
];
