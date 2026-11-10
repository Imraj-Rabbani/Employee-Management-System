<?php

use App\Core\Tenancy\EnsureModuleEnabled;
use App\Core\Tenancy\IdentifyTenant;
use App\Http\Middleware\SetLocale;
use App\Modules\Core\Http\Middleware\ApplyTenantSettings;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Routing\Middleware\SubstituteBindings;

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
            // Applies the company's timezone (Setup → Company Settings). Runs right after "tenant".
            'tenant.settings' => ApplyTenantSettings::class,
        ]);

        // "tenant" must run BEFORE route model binding (SubstituteBindings, part of "web"): a URL like
        // /hrm/employees/{employee} loads the Employee from the company database, which only exists once
        // "tenant" has switched to it. Laravel only reorders middleware that are in its priority list.
        $middleware->prependToPriorityList(before: SubstituteBindings::class, prepend: IdentifyTenant::class);

        // Every page: the logged-in user's language (Settings → Profile).
        $middleware->web(append: [SetLocale::class]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
