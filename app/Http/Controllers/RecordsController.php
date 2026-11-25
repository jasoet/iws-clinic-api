<?php

namespace App\Http\Controllers;

use App\Contract\RowNormalizer;
use App\Contract\RowValidator;
use App\Contract\Types;
use App\Queries\QueryResolver;
use App\Support\Cursor;
use App\Support\Pager;
use App\Support\Window;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/** GET /api/v1/{type}: one page of one record type inside a window. */
final class RecordsController
{
    public function __construct(
        private readonly QueryResolver $queries,
        private readonly Pager $pager,
        private readonly RowValidator $validator,
    ) {}

    public function __invoke(Request $request, string $type): JsonResponse
    {
        $window = Window::fromRequest($request->query(), (int) config('iws.max_window_days'));
        $after = $window->cursor === null ? null : Cursor::decode($window->cursor, $type, $window);
        $key = Types::get($type)['key'];
        $query = $this->queries->for($type);
        $normalizer = new RowNormalizer(config('iws.timezone'));

        $page = $this->pager->page($query->sql(), $key, $window->params(), $window->limit, $after);

        $data = [];
        $skipped = [];
        foreach ($page['rows'] as $raw) {
            $row = $normalizer->normalize($type, $query->transform($raw));
            $reasons = $this->validator->validate($type, $row);
            if ($reasons === []) {
                $data[] = $row;

                continue;
            }
            $skip = ['key' => isset($raw[$key]) ? (string) $raw[$key] : null, 'reason' => implode('; ', $reasons)];
            $skipped[] = $skip;
            // Type, key and reason only: never the row's data.
            Log::warning('iws: skipped an invalid row', ['type' => $type] + $skip);
        }

        return new JsonResponse([
            'data' => $data,
            'next_cursor' => $page['hasMore'] ? Cursor::encode($type, $window, $page['lastKey']) : null,
            'window' => ['from' => $window->from->toRfc3339String(), 'to' => $window->to->toRfc3339String()],
            'skipped' => $skipped,
        ]);
    }
}
