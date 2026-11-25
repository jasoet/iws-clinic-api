<?php

use Illuminate\Support\Facades\DB;
use Tests\Fixtures\Queries\ConditionQuery;
use Tests\Fixtures\Queries\LocationQuery;
use Tests\Fixtures\Queries\ObservationQuery;
use Tests\Fixtures\Queries\PatientQuery;
use Tests\Fixtures\Queries\PractitionerQuery;
use Tests\Fixtures\Queries\PresenceQuery;
use Tests\Fixtures\Queries\VisitQuery;

/**
 * A tiny clinic in SQLite for the framework tests. Window used by the tests:
 * [2026-11-01, 2026-12-01). In it: 5 visits (v4 cancelled), patients RM1 and RM2
 * with non-cancelled visits (RM3's only visit is cancelled), dokter 1 and 2,
 * poli 1, 6 observations, 2 diagnoses. v6 is outside the window.
 */
function seedFixtures(): void
{
    foreach ([
        'CREATE TABLE pasien (no_rm TEXT PRIMARY KEY, nik TEXT, nama TEXT, jk TEXT, lahir TEXT)',
        'CREATE TABLE dokter (id INTEGER PRIMARY KEY, nik TEXT, nama TEXT)',
        'CREATE TABLE poli (id INTEGER PRIMARY KEY, nama TEXT)',
        'CREATE TABLE kunjungan (id INTEGER PRIMARY KEY, no_rm TEXT, dokter_id INTEGER, poli_id INTEGER, status TEXT, masuk TEXT, keluar TEXT)',
        'CREATE TABLE pemeriksaan (id TEXT PRIMARY KEY, kunjungan_id INTEGER, kategori TEXT, kode TEXT, nama TEXT, nilai TEXT, satuan TEXT, flag TEXT, waktu TEXT)',
        'CREATE TABLE diagnosa (id INTEGER PRIMARY KEY, kunjungan_id INTEGER, icd TEXT, nama TEXT, waktu TEXT)',
    ] as $ddl) {
        DB::statement($ddl);
    }

    DB::insert("INSERT INTO pasien VALUES
        ('RM1', '3404010101900001', 'Ani', 'P', '1990-01-01'),
        ('RM2', NULL, 'Budi', 'L', '1985-05-05'),
        ('RM3', NULL, 'Citra', 'P', '')");
    DB::insert("INSERT INTO dokter VALUES (1, NULL, 'dr. Dewi'), (2, NULL, 'dr. Eko'), (3, NULL, 'dr. Fajar')");
    DB::insert("INSERT INTO poli VALUES (1, 'Medical Check Up')");
    DB::insert("INSERT INTO kunjungan VALUES
        (1, 'RM1', 1, 1, 'selesai', '2026-11-10 08:00:00', '2026-11-10 12:00:00'),
        (2, 'RM1', 1, 1, 'selesai', '2026-11-11 08:00:00', NULL),
        (3, 'RM2', 2, 1, 'aktif',   '2026-11-12 08:00:00', NULL),
        (4, 'RM3', 3, 1, 'batal',   '2026-11-13 08:00:00', NULL),
        (5, 'RM2', 2, 1, 'selesai', '2026-11-14 08:00:00', NULL),
        (6, 'RM1', 1, 1, 'selesai', '2026-10-01 08:00:00', NULL)");
    DB::insert("INSERT INTO pemeriksaan VALUES
        ('1',     1, 'vital-signs', 'heart_rate',  'Nadi',    '80',  '/min',  NULL, '2026-11-10 08:10:00'),
        ('2',     1, 'vital-signs', 'systolic_bp', 'Sistole', '120', 'mm[Hg]', NULL, '2026-11-10 08:10:00'),
        ('lab:1', 1, 'laboratory',  'GLU', 'Glukosa', '90',  'mg/dL', 'n', '2026-11-10 09:00:00'),
        ('lab:2', 2, 'laboratory',  'HB',  'Hemoglobin', '13.5', 'g/dL', '', '2026-11-11 09:00:00'),
        ('lab:3', 3, 'laboratory',  'GLU', 'Glukosa', '150', 'mg/dL', 'H', '2026-11-12 09:00:00'),
        ('lab:4', 4, 'laboratory',  'GLU', 'Glukosa', '99',  'mg/dL', NULL, '2026-11-13 09:00:00'),
        ('3',     5, 'vital-signs', 'spo2', 'SpO2', '98', '%', NULL, '2026-11-14 08:10:00')");
    DB::insert("INSERT INTO diagnosa VALUES
        (1, 1, 'Z00.0', 'Pemeriksaan umum', '2026-11-10 11:00:00'),
        (2, 3, 'E11',   'Diabetes', '2026-11-12 11:00:00'),
        (3, 4, 'I10',   'Hipertensi', '2026-11-13 11:00:00')");

    config(['iws.queries' => [
        'patients' => PatientQuery::class,
        'visits' => VisitQuery::class,
        'practitioners' => PractitionerQuery::class,
        'locations' => LocationQuery::class,
        'observations' => ObservationQuery::class,
        'conditions' => ConditionQuery::class,
        'presence' => PresenceQuery::class,
    ]]);
}
