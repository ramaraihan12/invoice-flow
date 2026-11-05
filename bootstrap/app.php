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
    ->withMiddleware(function (Middleware $middleware): void {
        // Authenticated by its signature. The route is a 404 outside the demo.
        $middleware->validateCsrfTokens(except: ['demo/webhook']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
