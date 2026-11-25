<?php

use App\Queries\ConditionQuery;
use App\Queries\LocationQuery;
use App\Queries\ObservationQuery;
use App\Queries\PatientQuery;
use App\Queries\PractitionerQuery;
use App\Queries\PresenceQuery;
use App\Queries\VisitQuery;

/*
| Settings for the API iws calls. Everything here comes from .env; you should
| not need to edit this file. See README.md, "The rules".
*/

$list = fn (?string $value): array => array_values(array_filter(array_map('trim', explode(',', (string) $value))));

return [
    // Bearer keys iws may use. Two at once lets you rotate without downtime.
    // Empty means every request is refused.
    'api_keys' => $list(env('IWS_API_KEYS')),

    // Client IPs allowed to call the API. Empty means any IP (the key still applies).
    'allowed_ips' => $list(env('IWS_ALLOWED_IPS')),

    // The widest window one request may ask for, in days.
    'max_window_days' => (int) env('IWS_MAX_WINDOW_DAYS', 31),

    // The time zone your database stores times in when a column has no zone.
    'timezone' => env('DB_TIMEZONE', 'Asia/Jakarta'),

    // Which query class answers each record type. Edit the classes, not this map.
    'queries' => [
        'patients' => PatientQuery::class,
        'visits' => VisitQuery::class,
        'practitioners' => PractitionerQuery::class,
        'locations' => LocationQuery::class,
        'observations' => ObservationQuery::class,
        'conditions' => ConditionQuery::class,
        'presence' => PresenceQuery::class,
    ],

    'version' => '1.0.0',
    'contract' => 'v1',
];
