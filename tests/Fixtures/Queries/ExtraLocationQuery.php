<?php

namespace Tests\Fixtures\Queries;

use App\Queries\BaseQuery;

class ExtraLocationQuery extends BaseQuery
{
    public function sql(): string
    {
        return "SELECT CAST(p.id AS TEXT) AS location_key, p.nama AS name, 'x' AS poli_type
            FROM poli p JOIN kunjungan k ON k.poli_id = p.id
            WHERE k.masuk >= :from AND k.masuk < :to GROUP BY p.id, p.nama";
    }
}
