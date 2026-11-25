<?php

use App\Support\SqlBinder;

it('binds in order of appearance', fn () => expect(SqlBinder::compile('a > :from AND a < :to AND b > :from', ['from' => 'F', 'to' => 'T']))
    ->toBe(['a > ? AND a < ? AND b > ?', ['F', 'T', 'F']]));

it('keeps postgres casts', fn () => expect(SqlBinder::compile('k.id::text = :key AND x::date IS NOT NULL', ['key' => 'K']))
    ->toBe(['k.id::text = ? AND x::date IS NOT NULL', ['K']]));

it('ignores colons in string literals', fn () => expect(SqlBinder::compile("x = ':from' AND y = 'it''s :to' AND z = :to", ['to' => 'T']))
    ->toBe(["x = ':from' AND y = 'it''s :to' AND z = ?", ['T']]));

it('ignores colons in comments', fn () => expect(SqlBinder::compile("-- note :from\nSELECT 1 /* :to */ WHERE a = :to", ['to' => 'T']))
    ->toBe(["-- note :from\nSELECT 1 /* :to */ WHERE a = ?", ['T']]));

it('ignores colons in quoted identifiers', fn () => expect(SqlBinder::compile('SELECT "a:b" FROM t WHERE c = :to', ['to' => 'T']))
    ->toBe(['SELECT "a:b" FROM t WHERE c = ?', ['T']]));

it('expands arrays', fn () => expect(SqlBinder::compile('id IN (:keys)', ['keys' => ['a', 'b']]))
    ->toBe(['id IN (?, ?)', ['a', 'b']]));

it('rejects unknown placeholders', fn () => SqlBinder::compile('a = :nope', []))
    ->throws(InvalidArgumentException::class, 'unknown placeholder :nope');

it('rejects an empty list', fn () => SqlBinder::compile('id IN (:keys)', ['keys' => []]))
    ->throws(InvalidArgumentException::class);
