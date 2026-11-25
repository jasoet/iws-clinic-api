<?php

namespace App\Contract;

/** One field of a record type: its name, kind, whether it must be present, and its allowed values. */
final readonly class Field
{
    /** @param list<string> $enum allowed values when kind is "enum" */
    public function __construct(
        public string $name,
        public string $kind, // string | date | datetime | enum | list
        public bool $required = false,
        public array $enum = [],
    ) {}
}
