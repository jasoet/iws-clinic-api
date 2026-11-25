<?php

use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Queries\BrokenQuery;
use Tests\Fixtures\Queries\DuplicateObservationQuery;
use Tests\Fixtures\Queries\ExtraLocationQuery;
use Tests\Fixtures\Queries\NoAdmittedVisitQuery;

beforeEach(fn () => seedFixtures());

$check = fn (array $options = []) => test()->artisan('iws:check', $options + ['--from' => '2026-11-01', '--to' => '2026-12-01']);

it('passes clean queries', function () use ($check) {
    $check()
        ->expectsOutputToContain('patients: 2 rows, OK')
        ->expectsOutputToContain('visits: 5 rows, OK')
        ->expectsOutputToContain('observations: 6 rows, OK')
        ->expectsOutputToContain('presence: 5 of 5 visit keys found, OK')
        ->assertExitCode(0);
});

it('checks only the types asked for', function () use ($check) {
    $check(['--type' => ['locations']])
        ->expectsOutputToContain('locations: 1 rows, OK')
        ->doesntExpectOutputToContain('visits:')
        ->assertExitCode(0);
});

it('reports a missing column', function () use ($check) {
    config(['iws.queries.visits' => NoAdmittedVisitQuery::class]);
    $check(['--type' => ['visits']])->expectsOutputToContain('visits: missing column admitted_at')->assertExitCode(1);
});

it('reports invalid rows with reasons', function () use ($check) {
    DB::insert("INSERT INTO kunjungan VALUES (7, 'RM2', 2, 1, 'weird', '2026-11-15 08:00:00', NULL)");
    $check(['--type' => ['visits']])
        ->expectsOutputToContain('visits: 6 rows, 1 invalid')
        ->expectsOutputToContain("key 7: status 'weird' is not one of in-progress, finished, cancelled")
        ->assertExitCode(1);
});

it('reports duplicate keys', function () use ($check) {
    config(['iws.queries.observations' => DuplicateObservationQuery::class]);
    $check(['--type' => ['observations']])->expectsOutputToContain('duplicate key')->assertExitCode(1);
});

it('warns about an extra column without failing', function () use ($check) {
    config(['iws.queries.locations' => ExtraLocationQuery::class]);
    $check(['--type' => ['locations']])->expectsOutputToContain('warning: extra column poli_type (ignored)')->assertExitCode(0);
});

it('says when a window has no rows', function () use ($check) {
    $check(['--from' => '2020-01-01', '--to' => '2020-01-02', '--type' => ['visits']])
        ->expectsOutputToContain('visits: no rows in window — columns not checked')
        ->assertExitCode(0);
});

it('shows the database error of a broken query', function () use ($check) {
    config(['iws.queries.visits' => BrokenQuery::class]);
    $check(['--type' => ['visits']])->expectsOutputToContain('no such table')->assertExitCode(1);
});

it('rejects an unknown type', function () use ($check) {
    $check(['--type' => ['medications']])->expectsOutputToContain("unknown type 'medications'")->assertExitCode(1);
});
