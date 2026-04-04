<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Inertia\Inertia;
use Illuminate\Support\Facades\Route;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Auth\Access\AuthorizationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        channels: __DIR__.'/../routes/channels.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        //
    })
    ->withExceptions(function (Exceptions $exceptions) {
        // Session expired or unauthenticated: redirect to login immediately
        $exceptions->renderable(function (AuthenticationException $e, $request) {
            // Inertia: force full-page redirect to login (client will follow X-Inertia-Location)
            if ($request->header('X-Inertia')) {
                return response('', 409)
                    ->header('X-Inertia-Location', route('login'));
            }
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Unauthenticated.'], 401);
            }
            return redirect()->guest(route('login'));
        });

        // CSRF/session token mismatch (e.g. session expired): redirect to login immediately
        $exceptions->renderable(function (TokenMismatchException $e, $request) {
            // Inertia: force full-page redirect to login (client will follow X-Inertia-Location)
            if ($request->header('X-Inertia')) {
                return response('', 409)
                    ->header('X-Inertia-Location', route('login'));
            }
            if ($request->expectsJson()) {
                return response()->json(['message' => 'Session expired.'], 419);
            }
            return redirect()->guest(route('login'))->with('error', __('Session expired. Please log in again.'));
        });

        // Handle authorization exceptions (403 Forbidden / Invalid Session)
        $exceptions->renderable(function (AuthorizationException $e, $request) {
            if ($request->header('X-Inertia')) {
                return response('', 409)
                    ->header('X-Inertia-Location', route('login'));
            }
            if ($request->expectsJson()) {
                return response()->json([
                    'error' => 'Access Denied',
                    'message' => 'Unauthorized or session invalid.',
                ], 403);
            }
            return redirect()->guest(route('login'))->with('error', 'Unauthorized or session invalid. Please log in again.');
        });

        $exceptions->renderable(function (HttpExceptionInterface $e, $request) {
            if ($e->getStatusCode() === 403) {
                if ($request->header('X-Inertia')) {
                    return response('', 409)
                        ->header('X-Inertia-Location', route('login'));
                }
                if ($request->is('login')) {
                    return Inertia::render('Auth/Login', [
                        'error' => $e->getMessage(),
                        'canResetPassword' => Route::has('password.request'),
                        'status' => session('status'),
                    ])->toResponse($request)->setStatusCode(403);
                }
                return redirect()->guest(route('login'))->with('error', 'Access denied or session invalid.');
            }
        });
    })->create();
