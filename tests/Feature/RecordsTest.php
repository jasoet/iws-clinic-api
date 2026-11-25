<?php

use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Queries\BrokenQuery;
use Tests\Fixtures\Queries\UpperLocationQuery;

beforeEach(fn () => seedFixtures());

it('lists each type inside the window', function (string $type, int $count) {
    list_($type)->assertOk()
        ->assertJsonCount($count, 'data')
        ->assertJsonPath('next_cursor', null)
        ->assertJsonPath('skipped', [])
        ->assertJsonPath('window', ['from' => '2026-11-01T00:00:00+07:00', 'to' => '2026-12-01T00:00:00+07:00']);
})->with([
    ['patients', 2], ['visits', 5], ['practitioners', 2], ['locations', 1], ['observations', 6], ['conditions', 2],
]);

it('returns records in the contract shape', function () {
    $visit = collect(list_('visits')->json('data'))->firstWhere('visit_key', '1');
    expect($visit)->toBe([
        'visit_key' => '1', 'patient_key' => 'RM1', 'practitioner_key' => '1', 'location_key' => '1',
        'class' => 'ambulatory', 'status' => 'finished',
        'admitted_at' => '2026-11-10T08:00:00+07:00', 'discharged_at' => '2026-11-10T12:00:00+07:00',
        'diagnosis_keys' => ['1'],
    ]);
});

it('returns cancelled visits with status cancelled', function () {
    $visit = collect(list_('visits')->json('data'))->firstWhere('visit_key', '4');
    expect($visit['status'])->toBe('cancelled')->and($visit['diagnosis_keys'])->toBe([]);
});

it('leaves out the outside-window visit', fn () => expect(array_column(list_('visits')->json('data'), 'visit_key'))->not->toContain('6'));

it('follows next_cursor to the end', function () {
    $keys = [];
    $cursor = null;
    $pages = 0;
    do {
        $body = list_('observations', array_filter(['limit' => 2, 'cursor' => $cursor]))->assertOk()->json();
        array_push($keys, ...array_column($body['data'], 'observation_key'));
        $cursor = $body['next_cursor'];
        $pages++;
    } while ($cursor !== null);

    expect($pages)->toBe(3)->and($keys)->toHaveCount(6)->and(array_unique($keys))->toHaveCount(6);
});

it('skips an invalid row and reports it', function () {
    DB::insert("INSERT INTO kunjungan VALUES (7, 'RM2', 2, 1, 'weird', '2026-11-15 08:00:00', NULL)");

    $body = list_('visits')->assertOk()->json();
    expect(array_column($body['data'], 'visit_key'))->not->toContain('7')->toHaveCount(5)
        ->and($body['skipped'])->toBe([[
            'key' => '7',
            'reason' => "status 'weird' is not one of in-progress, finished, cancelled",
        ]]);
});

it('applies transform', function () {
    config(['iws.queries.locations' => UpperLocationQuery::class]);
    list_('locations')->assertJsonPath('data.0.name', 'MEDICAL CHECK UP');
});

it('returns 400 for a bad window', fn () => list_('visits', ['from' => 'nope'])->assertStatus(400)
    ->assertHeader('Content-Type', 'application/problem+json'));

it('returns 400 for a cursor from another window', function () {
    $cursor = list_('observations', ['limit' => 2])->json('next_cursor');
    list_('observations', ['limit' => 2, 'cursor' => $cursor, 'from' => '2026-11-02T00:00:00+07:00'])->assertStatus(400);
});

it('returns 422 for a 32-day window', fn () => list_('visits', ['to' => '2026-12-03T00:00:00+07:00'])->assertStatus(422)
    ->assertJsonPath('status', 422));

it('returns 404 for an unknown type', fn () => list_('medications')->assertStatus(404));

it('needs the key', fn () => $this->getJson('/api/v1/visits?'.http_build_query(WINDOW))->assertStatus(401));

it('hides SQL on query failure', function () {
    config(['iws.queries.visits' => BrokenQuery::class]);
    $response = list_('visits')->assertStatus(500)->assertHeader('Content-Type', 'application/problem+json');
    expect($response->getContent())->not->toContain('SELECT')->not->toContain('no_such_table');
});

it('says which type has no query configured', function () {
    config(['iws.queries' => []]);
    list_('visits')->assertStatus(500);
});
