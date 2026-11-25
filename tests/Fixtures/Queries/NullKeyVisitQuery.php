<?php

namespace Tests\Fixtures\Queries;

/** Visits where visit 2 and 3 have no key (e.g. built through a LEFT JOIN). */
class NullKeyVisitQuery extends VisitQuery
{
    public function sql(): string
    {
        return 'SELECT CASE WHEN visit_key IN (\'2\', \'3\') THEN NULL ELSE visit_key END AS visit_key, patient_key, practitioner_key, location_key, class, status, admitted_at, discharged_at, diagnosis_keys FROM ('
            .parent::sql().') v';
    }
}
