<?php

namespace App\Queries;

/**
 * One record type's query. Your SQL returns one row per record, with a column
 * named after each field in the contract (README.md, bagian "4. Endpoint").
 *
 * Placeholders you can use: :from and :to (the window, as local time in
 * DB_TIMEZONE). Use them as many times as you like. The presence query uses
 * :visit_keys instead. Do not add ORDER BY or LIMIT: paging is done for you.
 */
interface ClinicQuery
{
    public function sql(): string;

    /** A last chance to fix up one row in PHP before it is checked. Most queries don't need it. */
    public function transform(array $row): array;
}
