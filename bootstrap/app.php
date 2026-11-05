<?php

use App\Core\Tenancy\EnsureModuleEnabled;
use App\Core\Tenancy\IdentifyTenant;
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
        // Route::middleware(['auth', 'tenant']) switches to the user's company database.
        $middleware->alias([
            'tenant' => IdentifyTenant::class,
            // Route::middleware('module:payroll') blocks the page when the company lacks that module.
            'module' => EnsureModuleEnabled::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
