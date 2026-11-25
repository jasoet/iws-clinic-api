<?php

namespace App\Support;

use InvalidArgumentException;

/**
 * Turns a query with named placeholders (:from, :to, :visit_keys) into one with
 * ? placeholders and the bindings in order.
 *
 * Why not let PDO bind names itself: PDO refuses the same name twice with real
 * prepared statements, and clinic SQL often needs :from in two places. The scan
 * skips string literals, quoted identifiers, comments and PostgreSQL casts
 * (k.id::text), so none of those is mistaken for a placeholder.
 */
final class SqlBinder
{
    /** @return array{0: string, 1: list<mixed>} */
    public static function compile(string $sql, array $params): array
    {
        $out = '';
        $bindings = [];
        $len = strlen($sql);
        $i = 0;

        while ($i < $len) {
            $c = $sql[$i];
            $next = $sql[$i + 1] ?? '';

            if ($c === "'" || $c === '"') {
                $end = self::quoteEnd($sql, $i, $c);
                $out .= substr($sql, $i, $end - $i);
                $i = $end;
            } elseif ($c === '-' && $next === '-') {
                $end = strpos($sql, "\n", $i);
                $end = $end === false ? $len : $end;
                $out .= substr($sql, $i, $end - $i);
                $i = $end;
            } elseif ($c === '/' && $next === '*') {
                $end = strpos($sql, '*/', $i + 2);
                $end = $end === false ? $len : $end + 2;
                $out .= substr($sql, $i, $end - $i);
                $i = $end;
            } elseif ($c === ':' && $next === ':') {
                $out .= '::';
                $i += 2;
            } elseif ($c === ':' && preg_match('/\G[a-z_][a-z0-9_]*/i', $sql, $m, 0, $i + 1)) {
                $name = $m[0];
                if (! array_key_exists($name, $params)) {
                    throw new InvalidArgumentException("unknown placeholder :$name");
                }
                $value = $params[$name];
                if (is_array($value)) {
                    if ($value === []) {
                        throw new InvalidArgumentException("placeholder :$name has an empty list");
                    }
                    $out .= implode(', ', array_fill(0, count($value), '?'));
                    array_push($bindings, ...array_values($value));
                } else {
                    $out .= '?';
                    $bindings[] = $value;
                }
                $i += 1 + strlen($name);
            } else {
                $out .= $c;
                $i++;
            }
        }

        return [$out, $bindings];
    }

    /** Position just past the closing quote; a doubled quote is an escaped one. */
    private static function quoteEnd(string $sql, int $start, string $quote): int
    {
        $i = $start + 1;
        $len = strlen($sql);
        while ($i < $len) {
            if ($sql[$i] === $quote) {
                if (($sql[$i + 1] ?? '') === $quote) {
                    $i += 2;

                    continue;
                }

                return $i + 1;
            }
            $i++;
        }

        return $len;
    }
}
