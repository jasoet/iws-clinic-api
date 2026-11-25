<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

final class HealthController
{
    public function __invoke(): JsonResponse
    {
        try {
            DB::select('SELECT 1');
            $database = 'ok';
        } catch (Throwable $e) {
            report($e);
            $database = 'down';
        }

        return new JsonResponse([
            'status' => $database === 'ok' ? 'ok' : 'down',
            'database' => $database,
            'version' => config('iws.version'),
            'contract' => config('iws.contract'),
        ], $database === 'ok' ? 200 : 503);
    }
}
