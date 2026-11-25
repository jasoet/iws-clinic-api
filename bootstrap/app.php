<?php

use App\Http\Middleware\IwsAuth;
use App\Http\Problem;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        api: __DIR__.'/../routes/api.php',
        apiPrefix: 'api/v1',
        commands: __DIR__.'/../routes/console.php',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias(['iws.auth' => IwsAuth::class]);

        // Behind a proxy, trust it so the IP allow-list sees iws's real address.
        $proxies = array_values(array_filter(array_map('trim', explode(',', (string) env('TRUSTED_PROXIES')))));
        if ($proxies !== []) {
            $middleware->trustProxies(at: $proxies);
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Every error under /api is problem+json. A server error never carries
        // SQL or data: the detail is generic and the exception goes to the log.
        $exceptions->render(function (Throwable $e, Request $request) {
            if (! $request->is('api/*')) {
                return null;
            }
            if ($e instanceof HttpExceptionInterface) {
                return Problem::response($e->getStatusCode(), detail: $e->getMessage());
            }
            if ($e instanceof ValidationException) {
                return Problem::response(400, detail: collect($e->errors())->flatten()->implode(' '));
            }

            return Problem::response(500, detail: 'The query failed. See the server log.');
        });
    })->create();
