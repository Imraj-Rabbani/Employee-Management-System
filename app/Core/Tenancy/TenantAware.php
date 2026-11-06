<?php

namespace App\Core\Tenancy;

use App\Models\Tenant;
use App\Modules\Core\Services\TenantPreferences;
use Closure;
use LogicException;

/**
 * Job middleware: switches to the job's tenant (company) database before
 * handle() runs, and switches back to "no tenant" afterwards.
 *
 * A queue worker is one long-running PHP process that runs jobs for every
 * company, one after another. Without this, a job would use whatever
 * database the previous job left behind, or none at all.
 *
 * Jobs don't use this class directly: they `use TenantAwareJob;`.
 */
class TenantAware
{
    /**
     * @param  object{tenantId: int|null}  $job
     * @param  Closure(object): mixed  $next
     */
    public function handle(object $job, Closure $next): mixed
    {
        if ($job->tenantId === null) {
            throw new LogicException($job::class.' was dispatched without a tenant. Dispatch it while a tenant is active.');
        }

        $tenants = app(TenantManager::class);

        // With the "sync" queue the job runs inside the web request that dispatched it,
        // so remember that request's tenant to put it back afterwards.
        $previous = $tenants->current();

        $tenants->switchTo(Tenant::findOrFail($job->tenantId));
        $preferences = app(TenantPreferences::class);
        $preferences->apply();   // the company's timezone, like ApplyTenantSettings does for web requests

        try {
            return $next($job);
        } finally {
            // Always clean up, even when the job throws, so the next job starts clean.
            if ($previous !== null) {
                $tenants->switchTo($previous);
                $preferences->apply();
            } else {
                $tenants->forget();
                $preferences->reset();
            }
        }
    }
}
