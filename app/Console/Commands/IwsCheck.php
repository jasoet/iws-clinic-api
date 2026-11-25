<?php

namespace App\Console\Commands;

use App\Contract\RowNormalizer;
use App\Contract\RowValidator;
use App\Contract\Types;
use App\Queries\QueryResolver;
use App\Support\Pager;
use App\Support\SqlBinder;
use App\Support\Window;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Runs every query against the real database and says what is wrong, before
 * iws connects. This is your own terminal, so database errors are shown in full.
 */
final class IwsCheck extends Command
{
    protected $signature = 'iws:check
        {--from= : window start (default: 7 days ago), e.g. 2026-11-01 or 2026-11-01T00:00:00+07:00}
        {--to= : window end, exclusive (default: now)}
        {--type=* : only these types (patients, visits, practitioners, locations, observations, conditions)}';

    protected $description = 'Check your queries against your database: columns, values, keys';

    private const SHOW_REASONS = 10;

    public function handle(QueryResolver $queries, Pager $pager, RowValidator $validator): int
    {
        $zone = config('iws.timezone');
        $now = CarbonImmutable::now($zone);
        try {
            $window = Window::fromRequest([
                'from' => $this->option('from') ?: $now->subDays(7)->toRfc3339String(),
                'to' => $this->option('to') ?: $now->toRfc3339String(),
            ], 3660);
        } catch (Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $types = $this->option('type') ?: array_keys(Types::all());
        foreach ($types as $type) {
            if (! Types::exists($type)) {
                $this->error("unknown type '$type'");

                return self::FAILURE;
            }
        }

        $this->line(sprintf('Window %s → %s (%s)', $window->from->toRfc3339String(), $window->to->toRfc3339String(), $zone));
        $ok = true;
        $visitKeys = [];
        foreach ($types as $type) {
            $result = $this->checkType($type, $window, $queries, $pager, $validator, new RowNormalizer($zone));
            $ok = $result['ok'] && $ok;
            if ($type === 'visits') {
                $visitKeys = $result['keys'];
            }
        }
        if (in_array('visits', $types, true) && $visitKeys !== []) {
            $ok = $this->checkPresence(array_slice($visitKeys, 0, 5), $queries) && $ok;
        }

        $ok ? $this->info('All good. Tell iws your API is ready.') : $this->error('Fix the problems above, then run this again.');

        return $ok ? self::SUCCESS : self::FAILURE;
    }

    /** @return array{ok: bool, keys: list<string>} */
    private function checkType(string $type, Window $window, QueryResolver $queries, Pager $pager, RowValidator $validator, RowNormalizer $normalizer): array
    {
        $spec = Types::get($type);
        $key = $spec['key'];
        $fields = array_map(fn ($f) => $f->name, $spec['fields']);

        $rows = [];
        try {
            $query = $queries->for($type);
            $after = null;
            do {
                $page = $pager->page($query->sql(), $key, $window->params(), Window::MAX_LIMIT, $after);
                array_push($rows, ...$page['rows']);
                $after = $page['lastKey'];
            } while ($page['hasMore']);
        } catch (Throwable $e) {
            $this->error("$type: query failed: ".$this->reason($e));

            return ['ok' => false, 'keys' => []];
        }

        if ($rows === []) {
            $this->line("$type: no rows in window — columns not checked");

            return ['ok' => true, 'keys' => []];
        }

        $columns = array_keys($rows[0]);
        $missing = array_diff($fields, $columns);
        foreach ($missing as $column) {
            $this->error("$type: missing column $column");
        }
        foreach (array_diff($columns, $fields) as $column) {
            $this->warn("$type: warning: extra column $column (ignored)");
        }
        if ($missing !== []) {
            return ['ok' => false, 'keys' => []];
        }

        $invalid = [];
        $seen = [];
        $duplicates = [];
        foreach ($rows as $raw) {
            $rowKey = trim((string) ($raw[$key] ?? ''));
            if (isset($seen[$rowKey])) {
                $duplicates[$rowKey] = true;
            }
            $seen[$rowKey] = true;
            try {
                $reasons = $validator->validate($type, $normalizer->normalize($type, $query->transform($raw)));
            } catch (Throwable $e) {
                $reasons = ['transform failed: '.$e->getMessage()];
            }
            if ($reasons !== []) {
                $invalid[] = "key $rowKey: ".implode('; ', $reasons);
            }
        }

        try {
            $nulls = $pager->nullKeys($query->sql(), $key, $window->params());
        } catch (Throwable $e) {
            $nulls = 0;
        }
        if ($nulls > 0) {
            $this->error("$type: ".Pager::nullKeysReason($nulls, $key));
        }

        $count = count($rows);
        if ($invalid === [] && $duplicates === [] && $nulls === 0) {
            $this->info("$type: $count rows, OK");

            return ['ok' => true, 'keys' => array_keys($seen)];
        }

        $this->error(sprintf('%s: %d rows, %d invalid, %d duplicate keys', $type, $count, count($invalid), count($duplicates)));
        foreach (array_slice($invalid, 0, self::SHOW_REASONS) as $reason) {
            $this->line("    $reason");
        }
        if (count($invalid) > self::SHOW_REASONS) {
            $this->line(sprintf('    … and %d more', count($invalid) - self::SHOW_REASONS));
        }
        foreach (array_slice(array_keys($duplicates), 0, self::SHOW_REASONS) as $dup) {
            $this->line("    duplicate key $dup (each record needs its own key)");
        }

        return ['ok' => false, 'keys' => array_keys($seen)];
    }

    /** @param list<string> $keys */
    private function checkPresence(array $keys, QueryResolver $queries): bool
    {
        try {
            [$sql, $bindings] = SqlBinder::compile($queries->for('presence')->sql(), ['visit_keys' => $keys]);
            $found = array_map(fn ($r) => trim((string) ((array) $r)['visit_key']), DB::select($sql, $bindings));
        } catch (Throwable $e) {
            $this->error('presence: query failed: '.$this->reason($e));

            return false;
        }
        $hit = count(array_intersect($keys, $found));
        $total = count($keys);
        if ($hit === $total) {
            $this->info("presence: $hit of $total visit keys found, OK");

            return true;
        }
        $this->error("presence: $hit of $total visit keys found; it must find every key the visits query returns");

        return false;
    }

    /** The database's own message, without the SQL Laravel appends. */
    private function reason(Throwable $e): string
    {
        $message = $e instanceof QueryException && $e->getPrevious() !== null ? $e->getPrevious()->getMessage() : $e->getMessage();

        return trim(strtok($message, "\n"));
    }
}
