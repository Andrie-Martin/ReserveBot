<?php

use App\Http\Middleware\AccountChecks;
use App\Http\Middleware\RequireConnectedEmail;
use App\Http\Middleware\RequireRole;
use App\Http\Middleware\SingleValueInputs;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Routing\Exceptions\InvalidSignatureException;

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
        // 'account': turned-off accounts are logged out; a password typed by an admin must be replaced first
        // 'email.connected': reps book only after connecting their Gmail or PUP webmail
        $middleware->alias([
            'role' => RequireRole::class,
            'account' => AccountChecks::class,
            'email.connected' => RequireConnectedEmail::class,
        ]);

        // A field sent as a list where one value is expected (?date[]=1) is treated as not sent, not a server error
        $middleware->web(prepend: [SingleValueInputs::class]);

        // A logged-in user who opens /login goes to their own home page
        $middleware->redirectUsersTo(fn (Request $request) => $request->user()->homeUrl());
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );

        // An emailed link that expired or was cut short: "connect your email", or a class's
        // face-to-face buttons (Phase 10), which work until the class ends
        $exceptions->render(fn (InvalidSignatureException $e, Request $request) => response()->view('auth.email_link', [
            'ok' => false,
            'message' => $request->is('class-check/*')
                ? 'This link has expired (it works until the class ends) or isn’t complete. Reps can also answer on their home page.'
                : 'This link has expired or isn’t complete. Log in and send a new one from My account.',
        ], 403));
    })->create();
