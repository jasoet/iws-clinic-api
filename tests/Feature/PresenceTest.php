<?php

use Tests\Fixtures\Queries\BrokenQuery;

beforeEach(fn () => seedFixtures());

$presence = fn (mixed $body) => test()->postJson('/api/v1/visits/presence', $body, withKey());

it('splits present and absent', fn () => $presence(['visit_keys' => ['3', '1', '999']])
    ->assertOk()->assertExactJson(['present' => ['1', '3'], 'absent' => ['999']]));

it('counts a cancelled visit as present', fn () => $presence(['visit_keys' => ['4']])
    ->assertExactJson(['present' => ['4'], 'absent' => []]));

it('counts a visit outside any window as present', fn () => $presence(['visit_keys' => ['6']])
    ->assertExactJson(['present' => ['6'], 'absent' => []]));

it('dedupes repeated keys', fn () => $presence(['visit_keys' => ['1', '1', '2']])
    ->assertExactJson(['present' => ['1', '2'], 'absent' => []]));

it('rejects more than 500 keys', fn () => $presence(['visit_keys' => array_map('strval', range(1, 501))])
    ->assertStatus(400)->assertHeader('Content-Type', 'application/problem+json'));

it('rejects a bad body', fn (mixed $body) => $presence($body)->assertStatus(400))->with([
    'missing' => [[]],
    'not a list' => [['visit_keys' => 'abc']],
    'empty' => [['visit_keys' => []]],
    'not strings' => [['visit_keys' => [['x']]]],
]);

it('fails whole on query error', function () use ($presence) {
    config(['iws.queries.presence' => BrokenQuery::class]);
    $response = $presence(['visit_keys' => ['1']])->assertStatus(500);
    expect($response->json())->not->toHaveKey('present');
});

it('needs the key', fn () => $this->postJson('/api/v1/visits/presence', ['visit_keys' => ['1']])->assertStatus(401));
