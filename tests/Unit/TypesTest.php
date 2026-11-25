<?php

use App\Contract\Field;
use App\Contract\Types;

// The spec's "Fields per type" section, in code. Change this only with the contract.
$expected = [
    'patients' => ['patient_key', [
        'patient_key' => ['string', true], 'nik' => ['string', false], 'name' => ['string', true],
        'gender' => ['enum', false, ['male', 'female', 'other', 'unknown']],
        'birth_date' => ['date', false], 'address' => ['string', false], 'phone' => ['string', false],
    ]],
    'visits' => ['visit_key', [
        'visit_key' => ['string', true], 'patient_key' => ['string', true],
        'practitioner_key' => ['string', false], 'location_key' => ['string', false],
        'class' => ['enum', true, ['ambulatory', 'emergency', 'inpatient']],
        'status' => ['enum', true, ['in-progress', 'finished', 'cancelled']],
        'admitted_at' => ['datetime', true], 'discharged_at' => ['datetime', false],
        'diagnosis_keys' => ['list', false],
    ]],
    'practitioners' => ['practitioner_key', [
        'practitioner_key' => ['string', true], 'nik' => ['string', false], 'name' => ['string', true],
    ]],
    'locations' => ['location_key', [
        'location_key' => ['string', true], 'name' => ['string', true],
    ]],
    'observations' => ['observation_key', [
        'observation_key' => ['string', true], 'visit_key' => ['string', true], 'patient_key' => ['string', true],
        'category' => ['enum', true, ['vital-signs', 'laboratory', 'exam', 'procedure', 'social-history']],
        'name' => ['string', true], 'loinc' => ['string', false], 'local_code' => ['string', false],
        'value' => ['string', true], 'unit' => ['string', false], 'reference_range' => ['string', false],
        'flag' => ['enum', false, ['N', 'H', 'L', 'A']],
        'effective_at' => ['datetime', true], 'issued_at' => ['datetime', false],
    ]],
    'conditions' => ['condition_key', [
        'condition_key' => ['string', true], 'visit_key' => ['string', true], 'patient_key' => ['string', true],
        'icd10' => ['string', true], 'name' => ['string', false], 'recorded_at' => ['datetime', true],
    ]],
];

it('defines exactly the six types', fn () => expect(array_keys(Types::all()))->toBe(array_keys($expected)));

it('matches the contract for each type', function (string $type) use ($expected) {
    [$key, $fields] = $expected[$type];
    $spec = Types::get($type);

    expect($spec['key'])->toBe($key);
    $actual = [];
    foreach ($spec['fields'] as $field) {
        expect($field)->toBeInstanceOf(Field::class);
        $actual[$field->name] = $field->enum === []
            ? [$field->kind, $field->required]
            : [$field->kind, $field->required, $field->enum];
    }
    expect($actual)->toBe($fields);
})->with(array_keys($expected));

it('lists the vital sign codes', fn () => expect(Types::VITAL_CODES)->toBe(
    ['heart_rate', 'respiratory_rate', 'body_temperature', 'systolic_bp', 'diastolic_bp', 'spo2']));

it('rejects an unknown type', fn () => Types::get('nope'))->throws(InvalidArgumentException::class);
