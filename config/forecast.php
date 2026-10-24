<?php

return [
    'version' => 'parish-sarima-v1',
    'horizon' => 6,
    'minimum_months' => 72,
    'services' => [
        'baptism' => ['order' => [0, 1, 1], 'seasonal_order' => [0, 1, 1, 12], 'log_transform' => false],
        'wedding' => ['order' => [0, 1, 1], 'seasonal_order' => [0, 1, 1, 12], 'log_transform' => true],
        'funeral' => ['order' => [1, 1, 1], 'seasonal_order' => [0, 1, 1, 12], 'log_transform' => false],
    ],
];
