<?php

use App\Support\BadRequest;
use App\Support\Window;
use App\Support\WindowTooWide;

beforeEach(fn () => config(['iws.timezone' => 'Asia/Jakarta']));

it('parses a window with defaults', function () {
    $w = Window::fromRequest(['from' => '2026-11-01T00:00:00+07:00', 'to' => '2026-11-02T00:00:00+07:00'], 31);
    expect($w->from->toRfc3339String())->toBe('2026-11-01T00:00:00+07:00')
        ->and($w->to->toRfc3339String())->toBe('2026-11-02T00:00:00+07:00')
        ->and($w->limit)->toBe(500)
        ->and($w->cursor)->toBeNull();
});

it('reads a time in another offset as the same instant', fn () => expect(
    Window::fromRequest(['from' => '2026-10-31T17:00:00Z', 'to' => '2026-11-01T17:00:00Z'], 31)->from->toRfc3339String())
    ->toBe('2026-11-01T00:00:00+07:00'));

it('repairs a + offset that arrived as a space', fn () => expect(
    Window::fromRequest(['from' => '2026-11-01T00:00:00 07:00', 'to' => '2026-11-02T00:00:00 07:00'], 31)->from->toRfc3339String())
    ->toBe('2026-11-01T00:00:00+07:00'));

it('formats the window for the database in the clinic zone', function () {
    $w = Window::fromRequest(['from' => '2026-10-31T17:00:00Z', 'to' => '2026-11-01T17:00:00Z'], 31);
    expect($w->params())->toBe(['from' => '2026-11-01 00:00:00', 'to' => '2026-11-02 00:00:00']);
});

it('rejects a bad window', fn (array $q) => Window::fromRequest($q, 31))->throws(BadRequest::class)->with([
    'missing from' => [['to' => '2026-11-02T00:00:00Z']],
    'missing to' => [['from' => '2026-11-02T00:00:00Z']],
    'garbage' => [['from' => 'yesterday-ish', 'to' => '2026-11-02T00:00:00Z']],
    'from after to' => [['from' => '2026-11-03T00:00:00Z', 'to' => '2026-11-02T00:00:00Z']],
    'from equals to' => [['from' => '2026-11-02T00:00:00Z', 'to' => '2026-11-02T00:00:00Z']],
    'limit zero' => [['from' => '2026-11-01T00:00:00Z', 'to' => '2026-11-02T00:00:00Z', 'limit' => '0']],
    'limit too big' => [['from' => '2026-11-01T00:00:00Z', 'to' => '2026-11-02T00:00:00Z', 'limit' => '1001']],
    'limit not a number' => [['from' => '2026-11-01T00:00:00Z', 'to' => '2026-11-02T00:00:00Z', 'limit' => '10abc']],
]);

it('rejects a window wider than the maximum', fn () => Window::fromRequest(
    ['from' => '2026-11-01T00:00:00Z', 'to' => '2026-12-03T00:00:00Z'], 31))->throws(WindowTooWide::class);

it('accepts exactly the maximum', fn () => expect(Window::fromRequest(
    ['from' => '2026-11-01T00:00:00Z', 'to' => '2026-12-02T00:00:00Z'], 31))->toBeInstanceOf(Window::class));
