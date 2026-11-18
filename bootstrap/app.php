<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use App\Http\Middleware\EnsureIdentityVerified;
use App\Http\Middleware\EnsureNotBlocked;
use App\Http\Middleware\EnsureRole;
use App\Http\Middleware\EnsureRoleChosen;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Alias = nama pendek yang dipakai di route. Nama-nama ini ditetapkan PRD.
        $middleware->alias([
            'role.chosen' => EnsureRoleChosen::class,
            'role' => EnsureRole::class,
            'identity.verified' => EnsureIdentityVerified::class,
            'not.blocked' => EnsureNotBlocked::class,
        ]);

                // Ke mana TAMU dikirim saat membuka halaman yang butuh login:
        // area Admin -> login Admin, selain itu -> login pengguna.
        $middleware->redirectGuestsTo(
            fn (Request $request) => $request->is('admin', 'admin/*')
                ? route('admin.login')
                : route('login')
        );

        // Ke mana pengguna yang SUDAH login dikirim saat membuka halaman khusus tamu
        // (middleware guest), misalnya halaman login.
        $middleware->redirectUsersTo(
            fn (Request $request) => $request->is('admin', 'admin/*') ? '/admin' : '/'
        );
    })

    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
