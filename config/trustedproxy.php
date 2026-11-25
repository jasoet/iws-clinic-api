<?php

// Proxies in front of the API (TRUSTED_PROXIES in .env), so the IP allow-list
// sees iws's real address. Read by Laravel's TrustProxies middleware.
return [
    'proxies' => array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES'))))) ?: null,
];
