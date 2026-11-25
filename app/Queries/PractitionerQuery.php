<?php

namespace App\Queries;

/**
 * GET /api/v1/practitioners: clinicians who attended a non-cancelled MCU visit
 * admitted in [:from, :to).
 *
 * ✎ This is GMC's query. Change names to yours, keep the column aliases.
 */
class PractitionerQuery extends BaseQuery
{
    public function sql(): string
    {
        return <<<'SQL'
SELECT
    d.dokter_id::text AS practitioner_key,
    d.nik             AS nik,
    d.nama            AS name
FROM dokter d
    INNER JOIN kunjungan k ON k.dokter_id = d.dokter_id
    -- ✎ which polis count as medical check-up at your clinic
    INNER JOIN poli mcu ON mcu.poli_id = k.poli_id AND mcu.nama IN ('Medical Check Up')
WHERE k.tanggal_masuk >= :from
  AND k.tanggal_masuk <  :to
  AND k.status <> 'batal'
GROUP BY d.dokter_id, d.nik, d.nama
SQL;
    }
}
