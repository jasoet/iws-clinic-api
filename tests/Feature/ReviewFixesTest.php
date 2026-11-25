<?php

use App\Contract\RowValidator;
use Tests\Fixtures\Queries\NullKeyVisitQuery;
use Tests\Fixtures\Queries\PaddedPresenceQuery;
use Tests\Fixtures\Queries\PaddedVisitQuery;
use Tests\Fixtures\Queries\ThrowingTransformLocationQuery;

beforeEach(fn () => seedFixtures());

it('matches padded char(n) keys in presence', function () {
    config(['iws.queries.presence' => PaddedPresenceQuery::class]);
    $this->postJson('/api/v1/visits/presence', ['visit_keys' => ['1', '999']], withKey())
        ->assertExactJson(['present' => ['1'], 'absent' => ['999']]);
});

it('checks presence with the keys iws will hold', function () {
    config(['iws.queries.visits' => PaddedVisitQuery::class]);
    $this->artisan('iws:check', ['--from' => '2026-11-01', '--to' => '2026-12-01', '--type' => ['visits']])
        ->expectsOutputToContain('presence: 5 of 5 visit keys found, OK')
        ->assertExitCode(0);
});

it('pages past rows without a key and reports them', function () {
    config(['iws.queries.visits' => NullKeyVisitQuery::class]);
    $keys = [];
    $skipped = [];
    $cursor = null;
    do {
        $body = list_('visits', array_filter(['limit' => 2, 'cursor' => $cursor]))->assertOk()->json();
        array_push($keys, ...array_column($body['data'], 'visit_key'));
        array_push($skipped, ...$body['skipped']);
        $cursor = $body['next_cursor'];
    } while ($cursor !== null);

    sort($keys);
    expect($keys)->toBe(['1', '4', '5'])
        ->and($skipped)->toBe([['key' => null, 'reason' => '2 rows have no visit_key and were left out']]);
});

it('reports rows without a key in iws:check', function () {
    config(['iws.queries.visits' => NullKeyVisitQuery::class]);
    $this->artisan('iws:check', ['--from' => '2026-11-01', '--to' => '2026-12-01', '--type' => ['visits']])
        ->expectsOutputToContain('2 rows have no visit_key')
        ->assertExitCode(1);
});

it('skips a row whose transform throws', function () {
    config(['iws.queries.locations' => ThrowingTransformLocationQuery::class]);
    list_('locations')->assertOk()
        ->assertJsonPath('data', [])
        ->assertJsonPath('skipped', [['key' => '1', 'reason' => 'transform failed: cannot read poli code']]);
});

it('names the row whose transform throws in iws:check', function () {
    config(['iws.queries.locations' => ThrowingTransformLocationQuery::class]);
    $this->artisan('iws:check', ['--from' => '2026-11-01', '--to' => '2026-12-01', '--type' => ['locations']])
        ->expectsOutputToContain('key 1: transform failed: cannot read poli code')
        ->assertExitCode(1);
});

it('accepts a vital sign identified by LOINC', fn () => expect((new RowValidator)->validate('observations', [
    'observation_key' => 'lab:9', 'visit_key' => '1', 'patient_key' => 'RM1', 'category' => 'vital-signs', 'name' => 'Berat badan',
    'loinc' => '29463-7', 'local_code' => 'BB', 'value' => '70', 'unit' => 'kg', 'reference_range' => null, 'flag' => null,
    'effective_at' => '2026-11-10T08:00:00+07:00', 'issued_at' => '2026-11-10T08:00:00+07:00',
]))->toBe([]));
