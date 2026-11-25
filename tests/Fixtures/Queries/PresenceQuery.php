<?php

namespace Tests\Fixtures\Queries;

use App\Queries\BaseQuery;

class PresenceQuery extends BaseQuery
{
    public function sql(): string
    {
        return <<<'SQL'
SELECT CAST(k.id AS TEXT) AS visit_key FROM kunjungan k WHERE CAST(k.id AS TEXT) IN (:visit_keys)
SQL;
    }
}
