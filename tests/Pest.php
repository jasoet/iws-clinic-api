<?php

use Illuminate\Testing\TestResponse;
use Tests\TestCase;

require_once __DIR__.'/Fixtures/seed.php';

pest()->extend(TestCase::class)->in('Feature', 'Unit', 'Gmc');

const TEST_KEY = 'change-me-test-key';

/** Configure one API key and return the bearer header for it. */
function withKey(string $key = TEST_KEY): array
{
    config(['iws.api_keys' => [$key]]);

    return ['Authorization' => 'Bearer '.$key];
}

const WINDOW = ['from' => '2026-11-01T00:00:00+07:00', 'to' => '2026-12-01T00:00:00+07:00'];

/** GET /api/v1/{type} with the test key and the fixture window (plus extra query params). */
function list_(string $type, array $query = []): TestResponse
{
    return test()->getJson('/api/v1/'.$type.'?'.http_build_query($query + WINDOW), withKey());
}
