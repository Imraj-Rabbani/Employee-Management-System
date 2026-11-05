<?php

namespace App\Core\Tenancy;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware "tenant": runs after "auth" and switches the app to the
 * logged-in user's company database for the rest of the request.
 *
 * Refuses the request (403) when the user has no company, or the company is
 * inactive or past its expiry date.
 */
class IdentifyTenant
{
    public function __construct(private TenantManager $tenants) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();
        $tenant = $user?->tenant;

        abort_if($tenant === null, 403, __('Your account is not linked to a company.'));

        $problem = $tenant->accessProblem();
        abort_if($problem !== null, 403, (string) $problem);

        $this->tenants->switchTo($tenant);

        return $next($request);
    }
}
