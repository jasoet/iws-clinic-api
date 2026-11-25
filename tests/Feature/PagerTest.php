<?php

use App\Support\Pager;
use Illuminate\Support\Facades\DB;

beforeEach(function () {
    DB::statement('CREATE TABLE t (k TEXT, at TEXT)');
    foreach (range(1, 25) as $i) {
        DB::insert('INSERT INTO t VALUES (?, ?)', [(string) $i, $i <= 20 ? '2026-11-01 10:00:00' : '2026-12-01 10:00:00']);
    }
});

$sql = 'SELECT k AS visit_key FROM t WHERE at >= :from AND at < :to';
$params = ['from' => '2026-11-01 00:00:00', 'to' => '2026-11-02 00:00:00'];

it('pages string keys without gaps', function () use ($sql, $params) {
    $seen = [];
    $after = null;
    do {
        $page = (new Pager)->page($sql, 'visit_key', $params, 7, $after);
        array_push($seen, ...array_column($page['rows'], 'visit_key'));
        $after = $page['lastKey'];
    } while ($page['hasMore']);

    sort($seen);
    $expected = array_map('strval', range(1, 20));
    sort($expected);
    expect($seen)->toBe($expected);
});

it('returns hasMore false on the last page', function () use ($sql, $params) {
    $page = (new Pager)->page($sql, 'visit_key', $params, 20, null);
    expect($page['hasMore'])->toBeFalse()->and($page['rows'])->toHaveCount(20);
});

it('returns hasMore true when rows remain', function () use ($sql, $params) {
    $page = (new Pager)->page($sql, 'visit_key', $params, 19, null);
    expect($page['hasMore'])->toBeTrue()->and($page['rows'])->toHaveCount(19)
        ->and($page['lastKey'])->toBe($page['rows'][18]['visit_key']);
});

it('applies the window params', function () use ($sql) {
    $page = (new Pager)->page($sql, 'visit_key', ['from' => '2026-12-01 00:00:00', 'to' => '2026-12-02 00:00:00'], 100, null);
    expect(array_column($page['rows'], 'visit_key'))->toBe(['21', '22', '23', '24', '25']);
});

it('survives a trailing semicolon and comment', function () use ($params) {
    $page = (new Pager)->page('SELECT k AS visit_key FROM t WHERE at >= :from AND at < :to; -- done', 'visit_key', $params, 100, null);
    expect($page['rows'])->toHaveCount(20);
});
