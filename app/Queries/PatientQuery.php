<?php

namespace App\Queries;

/**
 * GET /api/v1/patients: one row per patient with a non-cancelled MCU visit
 * admitted in [:from, :to).
 *
 * ✎ This is GMC's query. Change the table and column names to yours, keep the
 *   column aliases (AS patient_key, AS name, …): they are the contract.
 */
class PatientQuery extends BaseQuery
{
    public function sql(): string
    {
        return <<<'SQL'
SELECT
    p.no_rm         AS patient_key,   -- ✎ your medical record number; must never change
    p.nik           AS nik,
    p.nama          AS name,
    CASE p.jenis_kelamin              -- ✎ map your codes to male / female / other / unknown
        WHEN 'L' THEN 'male'
        WHEN 'P' THEN 'female'
        ELSE 'unknown'
    END             AS gender,
    p.tanggal_lahir AS birth_date,
    p.alamat        AS address,
    p.no_telepon    AS phone
FROM pasien p
    INNER JOIN kunjungan k ON k.pasien_id = p.pasien_id
    -- ✎ which polis count as medical check-up at your clinic
    INNER JOIN poli mcu ON mcu.poli_id = k.poli_id AND mcu.nama IN ('Medical Check Up')
WHERE k.tanggal_masuk >= :from
  AND k.tanggal_masuk <  :to
  AND k.status <> 'batal'             -- ✎ your "cancelled" status
GROUP BY p.no_rm, p.nik, p.nama, p.jenis_kelamin, p.tanggal_lahir, p.alamat, p.no_telepon
SQL;
    }
}
