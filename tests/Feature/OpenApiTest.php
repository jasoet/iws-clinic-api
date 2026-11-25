<?php

use App\Contract\Types;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Osteel\OpenApi\Testing\ValidatorBuilder;
use Symfony\Component\Yaml\Yaml;

beforeEach(fn () => seedFixtures());

$validator = fn () => ValidatorBuilder::fromYamlFile(base_path('openapi.yaml'))->getValidator();

it('matches the spec for each list endpoint', function (string $type) use ($validator) {
    $response = list_($type, ['limit' => 2])->assertOk();
    expect($validator()->validate($response->baseResponse, '/'.$type, 'get'))->toBeTrue();
})->with(array_keys(Types::all()));

it('matches the spec for a page with skipped rows', function () use ($validator) {
    DB::insert("INSERT INTO kunjungan VALUES (7, 'RM2', 2, 1, 'weird', '2026-11-15 08:00:00', NULL)");
    $response = list_('visits')->assertOk();
    expect($validator()->validate($response->baseResponse, '/visits', 'get'))->toBeTrue();
});

it('matches the spec for presence', function () use ($validator) {
    $response = $this->postJson('/api/v1/visits/presence', ['visit_keys' => ['1', '999']], withKey())->assertOk();
    expect($validator()->validate($response->baseResponse, '/visits/presence', 'post'))->toBeTrue();
});

it('matches the spec for health', function () use ($validator) {
    $response = $this->getJson('/api/v1/health', withKey())->assertOk();
    expect($validator()->validate($response->baseResponse, '/health', 'get'))->toBeTrue();
});

it('matches the spec for errors', function (int $status, Closure $call) use ($validator) {
    $response = $call()->assertStatus($status);
    expect($validator()->validate($response->baseResponse, '/visits', 'get'))->toBeTrue();
})->with([
    '400' => [400, fn () => list_('visits', ['from' => 'nope'])],
    '401' => [401, fn () => test()->getJson('/api/v1/visits?'.http_build_query(WINDOW))],
    '422' => [422, fn () => list_('visits', ['to' => '2026-12-03T00:00:00+07:00'])],
]);

it('lists exactly the contract fields in each schema', function (string $type) {
    $spec = Yaml::parseFile(base_path('openapi.yaml'));
    $schemaName = ['patients' => 'Patient', 'visits' => 'Visit', 'practitioners' => 'Practitioner',
        'locations' => 'Location', 'observations' => 'Observation', 'conditions' => 'Condition'][$type];
    $schema = $spec['components']['schemas'][$schemaName];

    $fields = Types::get($type)['fields'];
    expect(array_keys($schema['properties']))->toBe(array_map(fn ($f) => $f->name, $fields));
    expect($schema['required'])->toBe(array_values(array_map(fn ($f) => $f->name, array_filter($fields, fn ($f) => $f->required))));
    foreach ($fields as $f) {
        if ($f->enum !== []) {
            expect(array_values(array_filter($schema['properties'][$f->name]['enum'], fn ($v) => $v !== null)))->toBe($f->enum);
        }
    }
})->with(array_keys(Types::all()));

it('rejects a response that breaks the spec', function () use ($validator) {
    $bad = new JsonResponse(['data' => [['location_key' => '1', 'name' => 'X', 'extra' => 'y']],
        'next_cursor' => null, 'window' => ['from' => '2026-11-01T00:00:00+07:00', 'to' => '2026-12-01T00:00:00+07:00'], 'skipped' => []]);
    $validator()->validate($bad, '/locations', 'get');
})->throws(Exception::class);
