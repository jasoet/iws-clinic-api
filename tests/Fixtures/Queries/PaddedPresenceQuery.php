<?php

namespace Tests\Fixtures\Queries;

use App\Queries\BaseQuery;

/** Presence over a char(n)-style key: the database hands it back padded. */
class PaddedPresenceQuery extends BaseQuery
{
    public function sql(): string
    {
        return "SELECT CAST(k.id AS TEXT) || '   ' AS visit_key FROM kunjungan k WHERE CAST(k.id AS TEXT) IN (:visit_keys)";
    }
}
