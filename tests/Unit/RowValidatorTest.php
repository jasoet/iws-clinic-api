<?php

use App\Contract\RowValidator;

$valid = [
    'patients' => ['patient_key' => 'RM1', 'nik' => null, 'name' => 'A', 'gender' => 'female', 'birth_date' => '1990-02-03', 'address' => null, 'phone' => null],
    'visits' => ['visit_key' => '1', 'patient_key' => 'RM1', 'practitioner_key' => '2', 'location_key' => '3', 'class' => 'ambulatory', 'status' => 'finished',
        'admitted_at' => '2026-11-25T08:00:00+07:00', 'discharged_at' => null, 'diagnosis_keys' => ['4']],
    'practitioners' => ['practitioner_key' => '2', 'nik' => null, 'name' => 'dr. B'],
    'locations' => ['location_key' => '3', 'name' => 'Medical Check Up'],
    'observations' => ['observation_key' => 'lab:1', 'visit_key' => '1', 'patient_key' => 'RM1', 'category' => 'laboratory', 'name' => 'Glukosa',
        'loinc' => null, 'local_code' => 'GLU', 'value' => '90', 'unit' => 'mg/dL', 'reference_range' => '70-110', 'flag' => 'N',
        'effective_at' => '2026-11-25T08:00:00+07:00', 'issued_at' => '2026-11-25T09:00:00+07:00'],
    'conditions' => ['condition_key' => '4', 'visit_key' => '1', 'patient_key' => 'RM1', 'icd10' => 'E11', 'name' => null, 'recorded_at' => '2026-11-25T08:00:00+07:00'],
];

it('accepts a valid row', fn (string $type) => expect((new RowValidator)->validate($type, $valid[$type]))->toBe([]))
    ->with(array_keys($valid));

it('reports a missing required field', fn () => expect((new RowValidator)->validate('visits', ['visit_key' => null] + $valid['visits']))
    ->toBe(['visit_key is required']));

it('reports a value outside the list', fn () => expect((new RowValidator)->validate('visits', ['status' => 'batal'] + $valid['visits']))
    ->toBe(["status 'batal' is not one of in-progress, finished, cancelled"]));

it('reports a bad time', fn () => expect((new RowValidator)->validate('conditions', ['recorded_at' => 'kemarin'] + $valid['conditions']))
    ->toBe(["recorded_at 'kemarin' is not a date and time"]));

it('reports a bad date', fn () => expect((new RowValidator)->validate('patients', ['birth_date' => '03/02/1990'] + $valid['patients']))
    ->toBe(["birth_date '03/02/1990' is not a date (YYYY-MM-DD)"]));

it('reports an unknown vital sign code', fn () => expect((new RowValidator)->validate('observations',
    ['category' => 'vital-signs', 'local_code' => 'nadi'] + $valid['observations']))
    ->toBe(["local_code 'nadi' is not one of heart_rate, respiratory_rate, body_temperature, systolic_bp, diastolic_bp, spo2 (required for vital-signs)"]));
