<?php

namespace App\Contract;

use DateTimeImmutable;

/** Checks a normalized row against the contract. Returns the reasons it is invalid; empty means valid. */
final class RowValidator
{
    /** @return list<string> */
    public function validate(string $type, array $row): array
    {
        $reasons = [];
        foreach (Types::get($type)['fields'] as $field) {
            $value = $row[$field->name] ?? null;
            if ($value === null || $value === []) {
                if ($field->required) {
                    $reasons[] = "{$field->name} is required";
                }

                continue;
            }
            $reason = $this->check($field, $value);
            if ($reason !== null) {
                $reasons[] = $reason;
            }
        }

        if ($type === 'observations' && ($row['category'] ?? null) === 'vital-signs'
            && ! in_array($row['local_code'] ?? null, Types::VITAL_CODES, true)) {
            $reasons[] = sprintf("local_code '%s' is not one of %s (required for vital-signs)",
                $row['local_code'] ?? '', implode(', ', Types::VITAL_CODES));
        }

        return $reasons;
    }

    private function check(Field $field, mixed $value): ?string
    {
        return match ($field->kind) {
            'enum' => in_array($value, $field->enum, true) ? null
                : sprintf("%s '%s' is not one of %s", $field->name, $value, implode(', ', $field->enum)),
            'datetime' => DateTimeImmutable::createFromFormat(DATE_RFC3339, (string) $value) !== false ? null
                : "{$field->name} '$value' is not a date and time",
            'date' => preg_match('/^\d{4}-\d{2}-\d{2}$/', (string) $value) === 1 ? null
                : "{$field->name} '$value' is not a date (YYYY-MM-DD)",
            'list' => is_array($value) ? null : "{$field->name} is not a list",
            default => is_string($value) ? null : "{$field->name} is not text",
        };
    }
}
