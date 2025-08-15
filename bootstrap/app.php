<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__ . '/../routes/web.php',
        api: __DIR__ . '/../routes/api.php',      // <- habilita as rotas de API
        apiPrefix: 'api',                       // <- prefixo opcional (resulta em /api/...)
        commands: __DIR__ . '/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Aqui você pode registrar aliases de middleware para usar nas rotas de API:
        // $middleware->alias([
        //     'auth.token' => \App\Http\Middleware\AuthTokenMiddleware::class,
        //     'perm'       => \App\Http\Middleware\PermissionMiddleware::class,
        // ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
