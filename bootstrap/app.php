<?php

use App\Http\Middleware\SetSecurityHeaders;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(SetSecurityHeaders::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // A failed query can quote what a visitor typed, such as an enquiry's name, email or phone. The
        // connections mask bound values (`mask_bindings_in_exception_messages`); quoted values in the
        // database's own message and call arguments in the trace are left out here, so the log keeps the
        // query's shape and location but never its data.
        $exceptions->report(function (QueryException $exception): void {
            Log::error('Database query failed: '.preg_replace("/'(?:[^'\\\\]|\\\\.)*'/s", "'[redacted]'", $exception->getMessage()), [
                'exception' => $exception::class,
                'code' => $exception->getCode(),
                'connection' => $exception->getConnectionName(),
                'location' => $exception->getFile().':'.$exception->getLine(),
                'trace' => collect($exception->getTrace())->take(40)->map(fn (array $frame, int $index): string => '#'.$index.' '.($frame['file'] ?? '[internal]').'('.($frame['line'] ?? '?').'): '.($frame['class'] ?? '').($frame['type'] ?? '').$frame['function'].'()')->implode("\n"),
            ]);
        })->stop();
    })->create();
