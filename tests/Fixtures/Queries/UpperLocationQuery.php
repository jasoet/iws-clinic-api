<?php

namespace Tests\Fixtures\Queries;

class UpperLocationQuery extends LocationQuery
{
    public function transform(array $row): array
    {
        $row['name'] = strtoupper($row['name']);

        return $row;
    }
}
