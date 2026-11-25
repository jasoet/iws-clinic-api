<?php

namespace Tests\Fixtures\Queries;

use App\Queries\BaseQuery;

class BrokenQuery extends BaseQuery
{
    public function sql(): string
    {
        return <<<'SQL'
SELECT a FROM no_such_table WHERE a >= :from AND a < :to
SQL;
    }
}
