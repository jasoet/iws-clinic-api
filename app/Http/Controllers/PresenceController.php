<?php

namespace App\Http\Controllers;

use App\Queries\QueryResolver;
use App\Support\BadRequest;
use App\Support\SqlBinder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * POST /api/v1/visits/presence: which of these visit keys does the clinic
 * still hold? No window and no status filter: only a deleted visit is absent.
 * A failed query fails the whole request; half an answer would read as deletions.
 */
final class PresenceController
{
    public const MAX_KEYS = 500;

    public function __construct(private readonly QueryResolver $queries) {}

    public function __invoke(Request $request): JsonResponse
    {
        $keys = $this->keys($request->json('visit_keys'));

        [$sql, $bindings] = SqlBinder::compile($this->queries->for('presence')->sql(), ['visit_keys' => $keys]);
        $found = array_map(fn ($row) => (string) ((array) $row)['visit_key'], DB::select($sql, $bindings));

        $present = array_values(array_intersect($keys, $found));
        $absent = array_values(array_diff($keys, $found));
        sort($present, SORT_STRING);
        sort($absent, SORT_STRING);

        return new JsonResponse(['present' => $present, 'absent' => $absent]);
    }

    /** @return list<string> */
    private function keys(mixed $keys): array
    {
        if (! is_array($keys) || $keys === [] || ! array_is_list($keys)) {
            throw new BadRequest("'visit_keys' must be a non-empty list of keys.");
        }
        if (count($keys) > self::MAX_KEYS) {
            throw new BadRequest('Send at most '.self::MAX_KEYS.' keys per request.');
        }
        foreach ($keys as $key) {
            if (! is_string($key) && ! is_int($key)) {
                throw new BadRequest('Every visit key must be text.');
            }
        }

        return array_values(array_unique(array_map('strval', $keys)));
    }
}
