<?php

use Tests\TestCase;

pest()->extend(TestCase::class)->in('Feature', 'Unit', 'Gmc');

const TEST_KEY = 'change-me-test-key';

/** Configure one API key and return the bearer header for it. */
function withKey(string $key = TEST_KEY): array
{
    config(['iws.api_keys' => [$key]]);

    return ['Authorization' => 'Bearer '.$key];
}
