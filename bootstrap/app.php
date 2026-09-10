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
        $middleware->web(append: [
            \App\Http\Middleware\HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })
    ->booting(function (): void {
        if ($apiUrl = getenv('NATIVEPHP_API_URL')) {
            config(['nativephp-internal.api_url' => $apiUrl]);
        }
        if ($secret = getenv('NATIVEPHP_SECRET')) {
            config(['nativephp-internal.secret' => $secret]);
        }
        if ($storagePath = getenv('NATIVEPHP_STORAGE_PATH')) {
            config(['nativephp-internal.storage_path' => $storagePath]);
        }
        if ($databasePath = getenv('NATIVEPHP_DATABASE_PATH')) {
            config(['nativephp-internal.database_path' => $databasePath]);
        }
        if (getenv('NATIVEPHP_RUNNING')) {
            config(['nativephp-internal.running' => true]);
        }
    })->create();
