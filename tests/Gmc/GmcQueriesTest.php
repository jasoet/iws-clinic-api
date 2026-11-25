<?php

// The shipped GMC queries (app/Queries) against PostgreSQL loaded with GMC's
// mock schema and seed (database/gmc-example). Run with `task test:gmc`.

use Illuminate\Support\Facades\DB;

const K = '40000000-0000-0000-0000-00000000000';

beforeEach(function () {
    if (! env('GMC_TEST_DB_HOST')) {
        $this->markTestSkipped('Set GMC_TEST_DB_* (task test:gmc) to run the GMC query tests.');
    }
    config([
        'database.default' => 'pgsql',
        'database.connections.pgsql.host' => env('GMC_TEST_DB_HOST'),
        'database.connections.pgsql.port' => env('GMC_TEST_DB_PORT', '5432'),
        'database.connections.pgsql.database' => env('GMC_TEST_DB_DATABASE', 'gmc'),
        'database.connections.pgsql.username' => env('GMC_TEST_DB_USERNAME', 'postgres'),
        'database.connections.pgsql.password' => env('GMC_TEST_DB_PASSWORD', ''),
        'database.connections.pgsql.timezone' => 'Asia/Jakarta',
        'iws.timezone' => 'Asia/Jakarta',
    ]);
    DB::purge('pgsql');
    DB::unprepared('DROP SCHEMA IF EXISTS public CASCADE; CREATE SCHEMA public;');
    DB::unprepared(file_get_contents(base_path('database/gmc-example/schema.sql')));
    DB::unprepared(file_get_contents(base_path('database/gmc-example/seed.sql')));
})->group('gmc');

it('returns only MCU visits in the window', function () {
    $keys = array_column(list_('visits')->assertOk()->json('data'), 'visit_key');
    sort($keys);
    expect($keys)->toBe([K.'1', K.'2', K.'3', K.'5']);
})->group('gmc');

it('validates every row', function (string $type, int $count) {
    list_($type)->assertOk()->assertJsonPath('skipped', [])->assertJsonCount($count, 'data');
})->with([
    ['patients', 3], ['visits', 4], ['practitioners', 2], ['locations', 1], ['observations', 9], ['conditions', 1],
])->group('gmc');

it('maps GMC values to the contract', function () {
    $visit = collect(list_('visits')->json('data'))->keyBy('visit_key');
    expect($visit[K.'1'])->toMatchArray([
        'patient_key' => 'RM-0001', 'class' => 'ambulatory', 'status' => 'finished',
        'admitted_at' => '2026-11-10T08:00:00+07:00', 'discharged_at' => '2026-11-10T12:00:00+07:00',
        'diagnosis_keys' => ['80000000-0000-0000-0000-000000000001'],
    ])->and($visit[K.'2']['status'])->toBe('in-progress')
        ->and($visit[K.'2']['diagnosis_keys'])->toBe([]);

    $patient = collect(list_('patients')->json('data'))->keyBy('patient_key');
    expect($patient['RM-0001'])->toMatchArray(['gender' => 'female', 'birth_date' => '1990-01-01', 'phone' => null])
        ->and($patient['RM-0002']['gender'])->toBe('male');
})->group('gmc');

it('keeps pgsimrs keys', function () {
    $obs = collect(list_('observations')->json('data'));
    $vital = $obs->firstWhere('category', 'vital-signs');
    $lab = $obs->firstWhere('category', 'laboratory');
    expect($vital['observation_key'])->toMatch('/^[0-9a-f-]{36}$/')
        ->and($lab['observation_key'])->toStartWith('lab:7');
})->group('gmc');

it('maps vital signs and lab flags', function () {
    $obs = collect(list_('observations')->json('data'))->keyBy('observation_key');
    expect($obs['50000000-0000-0000-0000-000000000001'])->toMatchArray(['local_code' => 'heart_rate', 'value' => '80', 'flag' => null])
        ->and($obs['50000000-0000-0000-0000-000000000005']['local_code'])->toBe('diastolic_bp')
        ->and($obs['lab:70000000-0000-0000-0000-000000000001'])->toMatchArray([
            'loinc' => '1558-6', 'local_code' => 'GDP', 'value' => '130', 'flag' => 'H', 'reference_range' => '70-100',
            'effective_at' => '2026-11-10T08:30:00+07:00', 'issued_at' => '2026-11-10T10:00:00+07:00',
        ])
        ->and($obs['lab:70000000-0000-0000-0000-000000000002']['flag'])->toBe('N')
        ->and($obs['lab:70000000-0000-0000-0000-000000000004']['effective_at'])->toBe('2026-11-11T08:20:00+07:00')
        ->and($obs->keys())->not->toContain('lab:70000000-0000-0000-0000-000000000003');
})->group('gmc');

it('leaves out the patient of a cancelled-only visit', function () {
    expect(array_column(list_('patients')->json('data'), 'patient_key'))->not->toContain('RM-0003');
    $visit = collect(list_('visits')->json('data'))->firstWhere('visit_key', K.'3');
    expect($visit['status'])->toBe('cancelled');
})->group('gmc');

it('drops diagnoses without ICD-10', function () {
    expect(array_column(list_('conditions')->json('data'), 'condition_key'))->toBe(['80000000-0000-0000-0000-000000000001']);
})->group('gmc');

it('pages GMC observations without gaps', function () {
    $keys = [];
    $cursor = null;
    do {
        $body = list_('observations', array_filter(['limit' => 4, 'cursor' => $cursor]))->assertOk()->json();
        array_push($keys, ...array_column($body['data'], 'observation_key'));
        $cursor = $body['next_cursor'];
    } while ($cursor !== null);
    expect($keys)->toHaveCount(9)->and(array_unique($keys))->toHaveCount(9);
})->group('gmc');

it('answers presence for the cancelled visit as present', function () {
    $this->postJson('/api/v1/visits/presence', ['visit_keys' => [K.'3', K.'6', '49999999-0000-0000-0000-000000000000']], withKey())
        ->assertOk()
        ->assertExactJson(['present' => [K.'3', K.'6'], 'absent' => ['49999999-0000-0000-0000-000000000000']]);
})->group('gmc');
