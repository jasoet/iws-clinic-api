<?php

namespace App\Http;

use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

/** Builds RFC 9457 problem+json responses, the API's only error shape. */
final class Problem
{
    public static function response(int $status, string $title = '', string $detail = '', array $extra = []): JsonResponse
    {
        $body = [
            'type' => 'about:blank',
            'title' => $title !== '' ? $title : (Response::$statusTexts[$status] ?? 'Error'),
            'status' => $status,
        ];
        if ($detail !== '') {
            $body['detail'] = $detail;
        }

        return new JsonResponse($body + $extra, $status, ['Content-Type' => 'application/problem+json']);
    }
}
