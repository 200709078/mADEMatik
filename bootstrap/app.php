<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [\App\Http\Middleware\EnsureAccountActive::class, \App\Http\Middleware\SecurityHeaders::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
        $exceptions->respond(function (Response $response, \Throwable $exception, Request $request): Response {
            if (app()->isProduction() && $response->getStatusCode() >= 500) {
                if ($request->is('api/*') || $request->expectsJson()) {
                    $response = response()->json(['message' => __('ui.error_500_body')], $response->getStatusCode());
                }
                $response->headers->set('Cache-Control', 'private, no-store, max-age=0');
                $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
            }
            return $response;
        });
    })->create();
