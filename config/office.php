<?php

return [
    'timezone' => 'Asia/Manila',
    'analytics_enabled' => env('ANALYTICS_ENABLED', false),
    'demo' => env('ANALYTICS_DEMO', false),
    'python' => env('FORECAST_PYTHON', PHP_OS_FAMILY === 'Windows' ? storage_path('app/forecast-runtime/Scripts/python.exe') : storage_path('app/forecast-runtime/bin/python')),
    'forecast_timeout' => 50,
    'defaults' => [
        'parish_name' => 'St. John Nepomucene Parish',
        'opens_at' => '06:00',
        'closes_at' => '20:00',
        'operating_days' => [1, 2, 3, 4, 5, 6, 7],
        'analytics_start' => null,
        'missing_months' => [],
        'forecast_horizon' => 6,
        'training_months' => 60,
        'minimum_months' => 36,
        'order' => [1, 1, 1],
        'seasonal_order' => [0, 1, 1, 12],
    ],
    'permissions' => [
        'admin' => ['appointments.view', 'appointments.manage', 'documents.manage', 'payments.manage', 'records.view', 'records.manage', 'reports.view', 'finance.view', 'forecasts.manage', 'settings.manage', 'content.manage', 'audit.view', 'notifications.manage'],
        'staff' => ['appointments.view', 'appointments.manage', 'documents.manage', 'payments.manage', 'records.view', 'records.manage', 'reports.view', 'finance.view', 'notifications.manage'],
        'priest' => ['appointments.view', 'appointments.approve'],
    ],
];
