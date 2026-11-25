<?php

namespace App\Queries;

/**
 * GET /api/v1/locations: the poli/clinic of each non-cancelled MCU visit
 * admitted in [:from, :to).
 *
 * ✎ This is GMC's query. Change names to yours, keep the column aliases.
 */
class LocationQuery extends BaseQuery
{
    public function sql(): string
    {
        return <<<'SQL'
SELECT
    pol.poli_id::text AS location_key,
    pol.nama          AS name
FROM poli pol
    INNER JOIN kunjungan k ON k.poli_id = pol.poli_id
    -- ✎ which polis count as medical check-up at your clinic
    INNER JOIN poli mcu ON mcu.poli_id = k.poli_id AND mcu.nama IN ('Medical Check Up')
WHERE k.tanggal_masuk >= :from
  AND k.tanggal_masuk <  :to
  AND k.status <> 'batal'
GROUP BY pol.poli_id, pol.nama
SQL;
    }
}
