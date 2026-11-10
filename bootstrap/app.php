<?php

use App\Http\Middleware\EnsurePasswordIsChanged;
use App\Http\Middleware\RoleMiddleware;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'role' => RoleMiddleware::class,
            'password.changed' => EnsurePasswordIsChanged::class,
        ]);

        $middleware->append(SecurityHeaders::class);

        // Only answer for the host in APP_URL (and its subdomains), so a forged Host header can't redirect users elsewhere.
        // Laravel skips this check in the local environment and in tests.
        $middleware->trustHosts();

        $middleware->redirectGuestsTo(fn () => route('login'));

        $middleware->redirectUsersTo(function (Request $request) {
            $user = $request->user() ?? Auth::user();

            if (! $user) {
                return route('login');
            }

            if (method_exists($user, 'isAdmin') && $user->isAdmin()) {
                return route('admin.dashboard');
            }

            if (method_exists($user, 'isTeacher') && $user->isTeacher()) {
                return route('teacher.dashboard');
            }

            if (method_exists($user, 'isStudent') && $user->isStudent()) {
                return route('student.dashboard');
            }

            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return route('login');
        });
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
