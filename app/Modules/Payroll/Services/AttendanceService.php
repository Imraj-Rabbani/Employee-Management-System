<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Models\AttendanceDay;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

/**
 * Processes one employee's days and saves them in attendance_days: AttendanceInputs → AttendanceProcessor →
 * updateOrCreate. Used by the ProcessAttendance job (nightly, and later on every change, 7.5). [TA-05]
 *
 * Skipped: days before joining, after separation, in the future, and LOCKED days (payroll closed).
 */
class AttendanceService
{
    public function __construct(private AttendanceInputs $inputs, private AttendanceProcessor $processor) {}

    /** @return int how many days were (re)written */
    public function process(Employee $employee, CarbonImmutable $from, CarbonImmutable $to, ?CarbonImmutable $today = null): int
    {
        $today ??= CarbonImmutable::today();
        $from = $from->max(CarbonImmutable::parse($employee->join_date->toDateString()));
        $to = $to->min($today);
        if ($employee->separation_date !== null) {
            $to = $to->min(CarbonImmutable::parse($employee->separation_date->toDateString()));
        }
        if ($from->gt($to)) {
            return 0;
        }

        $locked = AttendanceDay::query()->where('employee_id', $employee->id)->where('is_locked', true)
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->pluck('date')->map(fn ($d) => Carbon::parse($d)->toDateString())->flip();

        $grace = $this->inputs->graceUsedBefore($employee, $from);
        $written = 0;
        foreach ($this->inputs->forEmployee($employee, $from, $to) as $input) {
            if ($input->date->day === 1) {
                $grace = 0;   // a new month: the grace limit starts again
            }
            $result = $this->processor->process($input->withGraceUsed($grace));
            $grace += $result->usedGrace ? 1 : 0;

            if ($locked->has($input->date->toDateString())) {
                continue;
            }
            AttendanceDay::updateOrCreate(
                ['employee_id' => $employee->id, 'date' => $input->date->toDateString()],
                [...$result->toAttributes(), 'processed_at' => now()],
            );
            $written++;
        }

        return $written;
    }
}
