<?php

namespace App\Queries;

abstract class BaseQuery implements ClinicQuery
{
    public function transform(array $row): array
    {
        return $row;
    }
}
