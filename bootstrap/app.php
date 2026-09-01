<?php

use App\Domain\Auth\DomainException;
use App\Domain\Common\Exceptions\StateTransitionException;
use App\Domain\Common\Exceptions\ValidationDomainException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->throttleApi('api');

        // Security headers (doc 14 §111)
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        // Guest users hitting auth-only web routes → login page
        $middleware->redirectGuestsTo(fn () => route('login'));
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->render(function (DomainException $e, Request $request) {
            return response()->json([
                'error' => [
                    'code' => $e->errorCode(),
                    'message' => $e->getMessage(),
                    'details' => $e->details(),
                    'reference_id' => (string) str()->uuid(),
                ],
            ], $e->status());
        });

        $exceptions->render(function (ValidationException $e, Request $request) {
            if ($request->is('api/*')) {
                return response()->json([
                    'error' => [
                        'code' => 'VALIDATION_FAILED',
                        'message' => 'The given data was invalid.',
                        'details' => $e->errors(),
                        'reference_id' => (string) str()->uuid(),
                    ],
                ], 422);
            }

            return null;
        });
    })->create();
