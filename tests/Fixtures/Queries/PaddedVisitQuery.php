<?php

namespace Tests\Fixtures\Queries;

/** Visits whose key comes back padded, as a char(n) column does. */
class PaddedVisitQuery extends VisitQuery
{
    public function sql(): string
    {
        return 'SELECT visit_key || \'   \' AS visit_key, patient_key, practitioner_key, location_key, class, status, admitted_at, discharged_at, diagnosis_keys FROM ('
            .parent::sql().') v';
    }
}
