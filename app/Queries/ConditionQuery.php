<?php

namespace App\Queries;

/**
 * GET /api/v1/conditions: ICD-10 diagnoses of non-cancelled MCU visits
 * admitted in [:from, :to). A diagnosis without an ICD-10 code is not sent.
 *
 * ✎ This is GMC's query. Change names to yours, keep the column aliases.
 */
class ConditionQuery extends BaseQuery
{
    public function sql(): string
    {
        return <<<'SQL'
SELECT
    dg.diagnosa_id::text AS condition_key,
    k.kunjungan_id::text AS visit_key,
    p.no_rm              AS patient_key,
    TRIM(dg.kode_icd10)  AS icd10,
    dg.nama_diagnosa     AS name,
    dg.waktu_diagnosa    AS recorded_at
FROM diagnosa dg
    INNER JOIN kunjungan k ON k.kunjungan_id = dg.kunjungan_id
    INNER JOIN pasien p ON p.pasien_id = k.pasien_id
    -- ✎ which polis count as medical check-up at your clinic
    INNER JOIN poli mcu ON mcu.poli_id = k.poli_id AND mcu.nama IN ('Medical Check Up')
WHERE k.tanggal_masuk >= :from
  AND k.tanggal_masuk <  :to
  AND k.status <> 'batal'
  AND NULLIF(TRIM(dg.kode_icd10), '') IS NOT NULL
SQL;
    }
}
