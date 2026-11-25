<?php

namespace Tests\Fixtures\Queries;

use App\Queries\BaseQuery;

class ObservationQuery extends BaseQuery
{
    public function sql(): string
    {
        return <<<'SQL'
SELECT o.id AS observation_key, CAST(k.id AS TEXT) AS visit_key, k.no_rm AS patient_key,
       o.kategori AS category, o.nama AS name, NULL AS loinc, o.kode AS local_code,
       o.nilai AS value, o.satuan AS unit, NULL AS reference_range, o.flag,
       o.waktu AS effective_at, NULL AS issued_at
FROM pemeriksaan o JOIN kunjungan k ON k.id = o.kunjungan_id
WHERE k.masuk >= :from AND k.masuk < :to AND k.status <> 'batal'
SQL;
    }
}
