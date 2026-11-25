<?php

use App\Contract\RowNormalizer;

$n = fn () => new RowNormalizer('Asia/Jakarta');

it('turns empty strings into null', fn () => expect($n()->normalize('patients', ['patient_key' => '1', 'name' => 'A', 'nik' => '  ', 'phone' => ''])['nik'])->toBeNull());

it('trims strings', fn () => expect($n()->normalize('locations', ['location_key' => '1', 'name' => ' Poli MCU '])['name'])->toBe('Poli MCU'));

it('reads a zoneless time in DB_TIMEZONE', fn () => expect($n()->normalize('conditions', ['recorded_at' => '2026-11-25 08:30:00'])['recorded_at'])
    ->toBe('2026-11-25T08:30:00+07:00'));

it('keeps an explicit offset', fn () => expect($n()->normalize('conditions', ['recorded_at' => '2026-11-25 01:30:00+00'])['recorded_at'])
    ->toBe('2026-11-25T08:30:00+07:00'));

it('formats dates as Y-m-d', fn () => expect($n()->normalize('patients', ['birth_date' => '1990-02-03 00:00:00'])['birth_date'])->toBe('1990-02-03'));

it('leaves an unparseable time for the validator', fn () => expect($n()->normalize('conditions', ['recorded_at' => 'kemarin'])['recorded_at'])->toBe('kemarin'));

it('splits diagnosis_keys', function () use ($n) {
    expect($n()->normalize('visits', ['diagnosis_keys' => '3, 7'])['diagnosis_keys'])->toBe(['3', '7']);
    expect($n()->normalize('visits', ['diagnosis_keys' => null])['diagnosis_keys'])->toBe([]);
    expect($n()->normalize('visits', ['diagnosis_keys' => ''])['diagnosis_keys'])->toBe([]);
});

it('casts keys and values to strings', function () use ($n) {
    $row = $n()->normalize('observations', ['observation_key' => 123, 'value' => 5.4]);
    expect($row['observation_key'])->toBe('123')->and($row['value'])->toBe('5.4');
});

it('fills issued_at from effective_at when null', fn () => expect($n()->normalize('observations', ['effective_at' => '2026-11-25 08:00:00', 'issued_at' => null])['issued_at'])
    ->toBe('2026-11-25T08:00:00+07:00'));

it('uppercases flag', fn () => expect($n()->normalize('observations', ['flag' => 'h'])['flag'])->toBe('H'));

it('returns exactly the contract fields in contract order', function () use ($n) {
    $row = $n()->normalize('locations', ['name' => 'X', 'extra' => 'y', 'location_key' => '9']);
    expect($row)->toBe(['location_key' => '9', 'name' => 'X']);
});
