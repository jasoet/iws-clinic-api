<?php

namespace Tests\Fixtures\Queries;

class DuplicateObservationQuery extends ObservationQuery
{
    public function sql(): string
    {
        return parent::sql()."\nUNION ALL\n".parent::sql();
    }
}
