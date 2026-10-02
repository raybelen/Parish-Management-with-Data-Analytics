<?php

$trustedProxies = trim((string) env('TRUSTED_PROXIES', ''));

return [
    'trusted_proxies' => match ($trustedProxies) {
        '*', '**' => $trustedProxies,
        '' => [],
        default => array_values(array_filter(array_map(trim(...), explode(',', $trustedProxies)))),
    },
];
