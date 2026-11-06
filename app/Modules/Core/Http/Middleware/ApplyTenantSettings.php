<?php

namespace App\Modules\Core\Http\Middleware;

use App\Modules\Core\Services\TenantPreferences;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware "tenant.settings": runs right after "tenant" (IdentifyTenant)
 * and applies the company's timezone for the rest of the request. (Architecture: LoadTenantSettings.)
 */
class ApplyTenantSettings
{
    public function __construct(private TenantPreferences $preferences) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (tenant() !== null) {
            $this->preferences->apply();
        }

        return $next($request);
    }
}
