<?php

namespace App\Modules\Payroll\Jobs;

use App\Core\Tenancy\TenantAwareJob;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Payroll\Services\AttendanceService;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Queued: (re)process one employee's attendance for a range of days. One job per employee, so a big
 * company's night run is spread over the queue workers and one bad employee does not stop the rest. [TA-05]
 *
 *     ProcessAttendance::dispatch($employee->id, '2026-12-01', '2026-12-31');
 */
class ProcessAttendance implements ShouldQueue
{
    use Queueable;
    use TenantAwareJob;

    public function __construct(public int $employeeId, public string $from, public string $to)
    {
        $this->rememberTenant();
    }

    public function handle(AttendanceService $attendance): void
    {
        // Not through the visibility scope: a job has no logged-in user, and must see everyone.
        $employee = Employee::withoutGlobalScope(VisibleToCurrentUser::class)->find($this->employeeId);
        if ($employee === null) {
            return;   // deleted since the job was queued
        }

        $attendance->process($employee, CarbonImmutable::parse($this->from), CarbonImmutable::parse($this->to));
    }
}
