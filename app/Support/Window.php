<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Throwable;

/** The half-open time window [from, to) of one list request, plus its paging. */
final readonly class Window
{
    public const DEFAULT_LIMIT = 500;

    public const MAX_LIMIT = 1000;

    private const ISO = '/^\d{4}-\d{2}-\d{2}(T\d{2}:\d{2}(:\d{2}(\.\d+)?)?(Z|[+-]\d{2}:?\d{2})?)?$/';

    private function __construct(
        public CarbonImmutable $from,
        public CarbonImmutable $to,
        public int $limit,
        public ?string $cursor,
    ) {}

    public static function fromRequest(array $query, int $maxDays): self
    {
        $from = self::time($query, 'from');
        $to = self::time($query, 'to');
        if ($from >= $to) {
            throw new BadRequest("'from' must be before 'to'.");
        }
        if ($from->addDays($maxDays) < $to) {
            throw new WindowTooWide("The window is wider than $maxDays days. Ask for a shorter one.");
        }

        $limit = $query['limit'] ?? (string) self::DEFAULT_LIMIT;
        if (! is_string($limit) || ! ctype_digit($limit) || (int) $limit < 1 || (int) $limit > self::MAX_LIMIT) {
            throw new BadRequest("'limit' must be a whole number from 1 to ".self::MAX_LIMIT.'.');
        }

        $cursor = $query['cursor'] ?? null;
        if ($cursor !== null && ! is_string($cursor)) {
            throw new BadRequest("'cursor' must be text.");
        }

        return new self($from, $to, (int) $limit, $cursor === '' ? null : $cursor);
    }

    /** The values bound to :from and :to: wall-clock time in the clinic's zone. */
    public function params(): array
    {
        return ['from' => $this->from->format('Y-m-d H:i:s'), 'to' => $this->to->format('Y-m-d H:i:s')];
    }

    private static function time(array $query, string $name): CarbonImmutable
    {
        $value = $query[$name] ?? null;
        if (! is_string($value) || $value === '') {
            throw new BadRequest("'$name' is required (RFC 3339, e.g. 2026-11-01T00:00:00+07:00).");
        }
        // An unencoded "+07:00" in a query string arrives as " 07:00".
        $value = preg_replace('/ (\d{2}:?\d{2})$/', '+$1', $value);
        if (! preg_match(self::ISO, $value)) {
            throw new BadRequest("'$name' is not an RFC 3339 time.");
        }
        $zone = config('iws.timezone');
        try {
            return CarbonImmutable::parse($value, $zone)->setTimezone($zone);
        } catch (Throwable) {
            throw new BadRequest("'$name' is not an RFC 3339 time.");
        }
    }
}
