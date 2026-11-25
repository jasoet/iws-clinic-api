<?php

namespace Tests\Fixtures\Queries;

use App\Queries\BaseQuery;

class LocationQuery extends BaseQuery
{
    public function sql(): string
    {
        return <<<'SQL'
SELECT CAST(p.id AS TEXT) AS location_key, p.nama AS name
FROM poli p JOIN kunjungan k ON k.poli_id = p.id
WHERE k.masuk >= :from AND k.masuk < :to AND k.status <> 'batal'
GROUP BY p.id, p.nama
SQL;
    }
}
