<?php

namespace App\Contract;

use Carbon\CarbonImmutable;
use Throwable;

/**
 * Turns one database row into the contract's shape: only the contract's
 * fields, in order; strings trimmed and "" as null; times as RFC 3339 in the
 * clinic's zone; dates as Y-m-d; diagnosis_keys as a list.
 *
 * It never refuses a row. A value it cannot convert is passed on unchanged so
 * RowValidator can say what is wrong with it.
 */
final class RowNormalizer
{
    public function __construct(private readonly string $timezone) {}

    public function normalize(string $type, array $row): array
    {
        $out = [];
        foreach (Types::get($type)['fields'] as $field) {
            $out[$field->name] = $this->value($field, $row[$field->name] ?? null);
        }
        if ($type === 'observations' && $out['issued_at'] === null) {
            $out['issued_at'] = $out['effective_at'];
        }

        return $out;
    }

    private function value(Field $field, mixed $value): mixed
    {
        if ($field->kind === 'list') {
            return $this->list($value);
        }
        $value = $this->text($value);
        if ($value === null) {
            return null;
        }

        return match ($field->kind) {
            'datetime' => $this->time($value, fn (CarbonImmutable $t) => $t->toRfc3339String()),
            'date' => $this->time($value, fn (CarbonImmutable $t) => $t->format('Y-m-d')),
            'enum' => $field->name === 'flag' ? strtoupper($value) : $value,
            default => $value,
        };
    }

    private function text(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof \DateTimeInterface) {
            $value = $value->format('Y-m-d H:i:sP');
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }

    /** @return list<string> */
    private function list(mixed $value): array
    {
        if (is_array($value)) {
            $items = $value;
        } else {
            $items = explode(',', (string) $this->text($value));
        }

        return array_values(array_filter(array_map(fn ($v) => trim((string) $v), $items), fn ($v) => $v !== ''));
    }

    private function time(string $value, callable $format): string
    {
        try {
            // A value without a zone is read as the clinic's local time.
            $t = CarbonImmutable::parse($value, $this->timezone)->setTimezone($this->timezone);
        } catch (Throwable) {
            return $value;
        }

        return $format($t);
    }
}
