<?php

namespace Tests\Fixtures\Queries;

use App\Queries\BaseQuery;

class PatientQuery extends BaseQuery
{
    public function sql(): string
    {
        return <<<'SQL'
SELECT p.no_rm AS patient_key, p.nik, p.nama AS name,
       CASE p.jk WHEN 'L' THEN 'male' WHEN 'P' THEN 'female' ELSE 'unknown' END AS gender,
       p.lahir AS birth_date, NULL AS address, NULL AS phone
FROM pasien p
JOIN kunjungan k ON k.no_rm = p.no_rm
WHERE k.masuk >= :from AND k.masuk < :to AND k.status <> 'batal'
GROUP BY p.no_rm, p.nik, p.nama, p.jk, p.lahir
SQL;
    }
}
