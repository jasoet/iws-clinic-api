<?php

namespace App\Queries;

/**
 * GET /api/v1/visits: every MCU visit admitted in [:from, :to), cancelled ones
 * included (with status 'cancelled', so the cancellation reaches the hospital).
 *
 * ✎ This is GMC's query. Change names to yours, keep the column aliases.
 */
class VisitQuery extends BaseQuery
{
    public function sql(): string
    {
        return <<<'SQL'
SELECT
    k.kunjungan_id::text AS visit_key,
    p.no_rm              AS patient_key,
    k.dokter_id::text    AS practitioner_key,   -- same key as practitioners returns
    k.poli_id::text      AS location_key,       -- same key as locations returns
    CASE k.tipe                                  -- ✎ map to ambulatory / emergency / inpatient
        WHEN 'rawat_jalan' THEN 'ambulatory'
        WHEN 'igd'         THEN 'emergency'
        WHEN 'rawat_inap'  THEN 'inpatient'
        ELSE k.tipe
    END                  AS class,
    CASE k.status                                -- ✎ map to in-progress / finished / cancelled
        WHEN 'aktif'   THEN 'in-progress'
        WHEN 'selesai' THEN 'finished'
        WHEN 'batal'   THEN 'cancelled'
        ELSE k.status
    END                  AS status,
    k.tanggal_masuk      AS admitted_at,
    k.tanggal_keluar     AS discharged_at,
    -- The visit's diagnoses, comma-separated, filtered exactly as ConditionQuery is.
    (SELECT string_agg(dg.diagnosa_id::text, ',' ORDER BY dg.diagnosa_id)
       FROM diagnosa dg
      WHERE dg.kunjungan_id = k.kunjungan_id
        AND k.status <> 'batal'
        AND NULLIF(TRIM(dg.kode_icd10), '') IS NOT NULL) AS diagnosis_keys
FROM kunjungan k
    LEFT JOIN pasien p ON p.pasien_id = k.pasien_id
    -- ✎ which polis count as medical check-up at your clinic
    INNER JOIN poli mcu ON mcu.poli_id = k.poli_id AND mcu.nama IN ('Medical Check Up')
WHERE k.tanggal_masuk >= :from
  AND k.tanggal_masuk <  :to
SQL;
    }
}
