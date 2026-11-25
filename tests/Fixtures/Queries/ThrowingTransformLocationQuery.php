<?php

namespace Tests\Fixtures\Queries;

use RuntimeException;

class ThrowingTransformLocationQuery extends LocationQuery
{
    public function transform(array $row): array
    {
        throw new RuntimeException('cannot read poli code');
    }
}
