<?php

namespace App\Core\Tenancy;

/**
 * Add to any queued job that reads or writes tenant data:
 *
 *     class ProcessAttendance implements ShouldQueue
 *     {
 *         use Queueable, TenantAwareJob;
 *
 *         public function __construct(public int $employeeId)
 *         {
 *             $this->rememberTenant();
 *         }
 *     }
 *
 * 1. Web request: the constructor stores the current tenant's id on the job.
 * 2. The id is saved with the job in the queue (jobs table).
 * 3. Worker: the TenantAware middleware switches to that tenant before handle().
 *
 * Forgetting rememberTenant() is not silent: the middleware throws.
 */
trait TenantAwareJob
{
    public ?int $tenantId = null;

    /** Call in the job's constructor, while the web request's tenant is still active. */
    public function rememberTenant(): void
    {
        $this->tenantId = tenant()?->id;
    }

    /** @return array<int, object> */
    public function middleware(): array
    {
        return [new TenantAware];
    }
}
