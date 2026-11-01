<?php

use App\Http\Middleware\RequireRole;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',   // webhook and /test-db
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: '',                        // keep them at /webhook and /test-db
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // ->middleware('role:lab_staff,admin') on a route = only those roles
        $middleware->alias(['role' => RequireRole::class]);

        // A logged-in user who opens /login goes to their own home page
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()->homeUrl());
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
