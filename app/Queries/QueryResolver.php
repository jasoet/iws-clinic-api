<?php

namespace App\Queries;

use LogicException;

/** Finds the query class configured for a record type (config/iws.php → queries). */
final class QueryResolver
{
    public function for(string $type): ClinicQuery
    {
        $class = config("iws.queries.$type");
        if (! is_string($class) || ! class_exists($class)) {
            throw new LogicException("No query is configured for '$type'. Set it in config/iws.php → queries.");
        }
        $query = app($class);
        if (! $query instanceof ClinicQuery) {
            throw new LogicException("$class must implement ".ClinicQuery::class.'.');
        }

        return $query;
    }
}
