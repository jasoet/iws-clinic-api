<?php

namespace App\Queries;

/**
 * POST /api/v1/visits/presence: which of :visit_keys does the database still
 * hold? A plain existence check: no window, no status filter, no MCU filter.
 * A cancelled visit is still present; only a deleted one is absent.
 *
 * ✎ Use the same key expression as VisitQuery's visit_key.
 */
class PresenceQuery extends BaseQuery
{
    public function sql(): string
    {
        return <<<'SQL'
SELECT k.kunjungan_id::text AS visit_key
FROM kunjungan k
WHERE k.kunjungan_id::text IN (:visit_keys)
SQL;
    }
}
