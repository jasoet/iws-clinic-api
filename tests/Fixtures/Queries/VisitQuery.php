<?php

namespace Tests\Fixtures\Queries;

use App\Queries\BaseQuery;

class VisitQuery extends BaseQuery
{
    public function sql(): string
    {
        return <<<'SQL'
SELECT CAST(k.id AS TEXT) AS visit_key, k.no_rm AS patient_key,
       CAST(k.dokter_id AS TEXT) AS practitioner_key, CAST(k.poli_id AS TEXT) AS location_key,
       'ambulatory' AS class,
       CASE k.status WHEN 'aktif' THEN 'in-progress' WHEN 'selesai' THEN 'finished' WHEN 'batal' THEN 'cancelled' ELSE k.status END AS status,
       k.masuk AS admitted_at, k.keluar AS discharged_at,
       (SELECT group_concat(d.id) FROM diagnosa d WHERE d.kunjungan_id = k.id AND k.status <> 'batal') AS diagnosis_keys
FROM kunjungan k
WHERE k.masuk >= :from AND k.masuk < :to
SQL;
    }
}
