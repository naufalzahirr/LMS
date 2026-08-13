<?php

use App\Http\Middleware\HandleAppearance;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\ThrottleSensitiveGuestRequests;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->encryptCookies(except: ['appearance', 'sidebar_state']);

        $middleware->web(append: [
            HandleAppearance::class,
            HandleInertiaRequests::class,
            AddLinkHeadersForPreloadedAssets::class,
            ThrottleSensitiveGuestRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        $exceptions->respond(function (Response $response): Response {
            $request = request();
            $status = $response->getStatusCode();

            // Inertia visits must always receive an Inertia error page. A raw
            // local HTML exception response is displayed by Inertia in its
            // development iframe overlay and can survive browser history
            // navigation over otherwise valid pages.
            if ((app()->environment(['local', 'testing']) && ! $request->header('X-Inertia'))
                || $request->expectsJson()
                || ! in_array($status, [403, 404, 419, 422, 500], true)) {
                return $response;
            }

            $messages = [
                403 => ['Access denied', 'You do not have permission to open this page.'],
                404 => ['Page not found', 'The page may have moved or no longer exists.'],
                419 => ['Session expired', 'Refresh the page, sign in again if needed, and retry your action.'],
                422 => ['Request could not be processed', 'Review your input and try again.'],
                500 => ['Something went wrong', 'The issue has been logged. Please try again shortly.'],
            ];

            return Inertia::render('Error', [
                'status' => $status,
                'title' => $messages[$status][0],
                'description' => $messages[$status][1],
            ])->toResponse($request)->setStatusCode($status);
        });
    })->create();
