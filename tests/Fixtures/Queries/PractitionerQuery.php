<?php

namespace Tests\Fixtures\Queries;

use App\Queries\BaseQuery;

class PractitionerQuery extends BaseQuery
{
    public function sql(): string
    {
        return <<<'SQL'
SELECT CAST(d.id AS TEXT) AS practitioner_key, d.nik, d.nama AS name
FROM dokter d JOIN kunjungan k ON k.dokter_id = d.id
WHERE k.masuk >= :from AND k.masuk < :to AND k.status <> 'batal'
GROUP BY d.id, d.nik, d.nama
SQL;
    }
}
