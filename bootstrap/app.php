<?php

use App\Http\Middleware\AuditChanges;
use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\RedirectAdminUsers;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/auth.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [AuditChanges::class]);
        $middleware->append(SecurityHeaders::class);
        $middleware->trustHosts(at: fn () => ['^'.preg_quote(parse_url(config('app.url'), PHP_URL_HOST) ?: 'localhost', '/').'$'], subdomains: false);
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
            'not_admin' => RedirectAdminUsers::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
