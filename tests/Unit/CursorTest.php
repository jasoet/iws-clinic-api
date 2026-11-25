<?php

use App\Support\BadRequest;
use App\Support\Cursor;
use App\Support\Window;

$w = fn (string $from = '2026-11-01T00:00:00Z') => Window::fromRequest(['from' => $from, 'to' => '2026-11-02T00:00:00Z'], 31);

it('round trips the last key', fn () => expect(Cursor::decode(Cursor::encode('visits', $w(), 'k-42'), 'visits', $w()))->toBe('k-42'));

it('is url safe', fn () => expect(Cursor::encode('visits', $w(), str_repeat('?/+', 20)))->toMatch('/^[A-Za-z0-9_-]+$/'));

it('rejects a cursor from another window', fn () => Cursor::decode(Cursor::encode('visits', $w(), 'k'), 'visits', $w('2026-11-01T01:00:00Z')))
    ->throws(BadRequest::class);

it('rejects a cursor from another type', fn () => Cursor::decode(Cursor::encode('visits', $w(), 'k'), 'patients', $w()))
    ->throws(BadRequest::class);

it('rejects garbage', fn () => Cursor::decode('not-a-cursor!!', 'visits', $w()))->throws(BadRequest::class);
