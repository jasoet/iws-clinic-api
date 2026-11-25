<?php

namespace App\Support;

/**
 * The opaque paging token. It carries the last key of the page and is bound to
 * the type and window it came from, so it can't page a different set.
 */
final class Cursor
{
    public static function encode(string $type, Window $w, string $lastKey): string
    {
        $json = json_encode(['t' => $type, 'f' => $w->from->getTimestamp(), 'to' => $w->to->getTimestamp(), 'k' => $lastKey]);

        return rtrim(strtr(base64_encode($json), '+/', '-_'), '=');
    }

    /** @return string the last key of the previous page */
    public static function decode(string $cursor, string $type, Window $w): string
    {
        $json = base64_decode(strtr($cursor, '-_', '+/'), true);
        $data = $json === false ? null : json_decode($json, true);
        if (! is_array($data) || ! isset($data['t'], $data['f'], $data['to'], $data['k']) || ! is_string($data['k'])) {
            throw new BadRequest("'cursor' is not one this API issued.");
        }
        if ($data['t'] !== $type || $data['f'] !== $w->from->getTimestamp() || $data['to'] !== $w->to->getTimestamp()) {
            throw new BadRequest("'cursor' belongs to a different type or window. Send the same 'from' and 'to' as the first page.");
        }

        return $data['k'];
    }
}
