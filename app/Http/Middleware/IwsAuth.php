<?php

namespace App\Http\Middleware;

use App\Http\Problem;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Lets a request through only with a configured bearer key, from an allowed IP. */
final class IwsAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowed = config('iws.allowed_ips', []);
        if ($allowed !== [] && ! in_array($request->ip(), $allowed, true)) {
            return Problem::response(403, detail: 'This address may not call the API.');
        }

        if (! $this->validKey((string) $request->bearerToken())) {
            return Problem::response(401, detail: 'A valid bearer key is required.')
                ->header('WWW-Authenticate', 'Bearer');
        }

        return $next($request);
    }

    private function validKey(string $given): bool
    {
        if ($given === '') {
            return false;
        }
        $ok = false;
        foreach (config('iws.api_keys', []) as $key) {
            // Check every key so the time taken doesn't hint which one nearly matched.
            $ok = hash_equals((string) $key, $given) || $ok;
        }

        return $ok;
    }
}
