<?php

namespace App\Core\Tenancy;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware "module:{name}", e.g. "module:payroll".
 * Runs after "tenant" and refuses (403) when the current company does not have that module switched on.
 *
 * ModuleServiceProvider adds it to every module's routes, so a module's pages
 * are blocked as soon as the super admin switches the module off.
 */
class EnsureModuleEnabled
{
    public function handle(Request $request, Closure $next, string $module): Response
    {
        abort_unless(tenant()?->hasModule($module), 403, __('Your company does not have this module.'));

        return $next($request);
    }
}
