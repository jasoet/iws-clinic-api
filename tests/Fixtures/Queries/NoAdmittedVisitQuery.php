<?php

namespace Tests\Fixtures\Queries;

use App\Queries\BaseQuery;

class NoAdmittedVisitQuery extends BaseQuery
{
    public function sql(): string
    {
        return "SELECT CAST(k.id AS TEXT) AS visit_key, k.no_rm AS patient_key, NULL AS practitioner_key, NULL AS location_key,
            'ambulatory' AS class, 'finished' AS status, NULL AS discharged_at, NULL AS diagnosis_keys
            FROM kunjungan k WHERE k.masuk >= :from AND k.masuk < :to";
    }
}
