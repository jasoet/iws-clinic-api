<?php

namespace App\Support;

use Illuminate\Support\Facades\DB;

/**
 * Runs a clinic query one page at a time, by key (keyset paging).
 *
 * The clinic's SQL is wrapped as a subquery:
 *   SELECT * FROM (<their SQL>) q WHERE q.<key> > <last key> ORDER BY q.<key> LIMIT n+1
 * The database both filters and orders by the key, so every row is visited
 * exactly once however the keys sort.
 */
final class Pager
{
    /** @return array{rows: list<array>, lastKey: ?string, hasMore: bool} */
    public function page(string $sql, string $keyColumn, array $params, int $limit, ?string $afterKey, ?string $connection = null): array
    {
        $db = DB::connection($connection);
        $key = 'q.'.$keyColumn; // from Types, never from the request
        $inner = rtrim(preg_replace('/;\s*(--[^\n]*)?\s*$/', '', trim($sql)));

        $wrapped = "SELECT * FROM (\n$inner\n) q";
        if ($afterKey !== null) {
            $wrapped .= " WHERE $key > :iws_after";
            $params['iws_after'] = $afterKey;
        }
        $n = $limit + 1;
        $wrapped .= $db->getDriverName() === 'sqlsrv'
            ? " ORDER BY $key OFFSET 0 ROWS FETCH NEXT $n ROWS ONLY"
            : " ORDER BY $key LIMIT $n";

        [$compiled, $bindings] = SqlBinder::compile($wrapped, $params);
        $rows = array_map(fn ($r) => (array) $r, $db->select($compiled, $bindings));

        $hasMore = count($rows) > $limit;
        $rows = array_slice($rows, 0, $limit);
        $last = $rows === [] ? null : (string) end($rows)[$keyColumn];

        return ['rows' => $rows, 'lastKey' => $last, 'hasMore' => $hasMore];
    }
}
