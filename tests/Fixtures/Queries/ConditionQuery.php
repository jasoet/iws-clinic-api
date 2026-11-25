<?php

namespace Tests\Fixtures\Queries;

use App\Queries\BaseQuery;

class ConditionQuery extends BaseQuery
{
    public function sql(): string
    {
        return <<<'SQL'
SELECT CAST(d.id AS TEXT) AS condition_key, CAST(k.id AS TEXT) AS visit_key, k.no_rm AS patient_key,
       d.icd AS icd10, d.nama AS name, d.waktu AS recorded_at
FROM diagnosa d JOIN kunjungan k ON k.id = d.kunjungan_id
WHERE k.masuk >= :from AND k.masuk < :to AND k.status <> 'batal'
SQL;
    }
}
