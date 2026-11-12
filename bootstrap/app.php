<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Yalnızca açıkça tanımlanmış ters vekillerin ilettiği HTTPS bilgisini kabul et.
        // Doğrudan Nginx/Apache kurulumunda bu değişken boş bırakılmalıdır.
        $trustedProxies = trim((string) env('TRUSTED_PROXIES', ''));
        if ($trustedProxies !== '') {
            $middleware->trustProxies(at: array_values(array_filter(array_map('trim', explode(',', $trustedProxies)))));
        }

        // Inertia middleware'ini web grubuna ekliyoruz
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);

        // Admin yetki kontrolünü tanıtıyoruz
        $middleware->alias([
            'admin' => \App\Http\Middleware\EnsureUserIsAdmin::class,
            'session.current' => \App\Http\Middleware\EnsureSessionIsCurrent::class,
            'password.changed' => \App\Http\Middleware\EnsurePasswordHasBeenChanged::class,
            'module.access' => \App\Http\Middleware\EnsureModuleAccess::class,
            'module.write' => \App\Http\Middleware\EnsureModuleWriteAccess::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
