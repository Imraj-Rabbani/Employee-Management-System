<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Payroll\Jobs\ProcessAttendance;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * "Process these employees' attendance again for these days": ONE place that queues ProcessAttendance jobs. [TA-05, TA-06]
 * Used by the nightly command, the Processing tab (HR picks a range) and every change that alters a past day:
 *
 *   roster approved         Roster::onApproved()
 *   holiday added / edited  HolidayForm
 *   new or matched punches  PunchLog::linkEmployees()   (uploads, device sync, "Match unmatched punches")
 *   correction approved     AttendanceCorrection::onApproved()   (7.6)
 *   leave approved / withdrawn                       → Phase 8 calls queue() the same way
 *
 *     app(AttendanceReprocessor::class)->queue([5, 8], '2026-12-01', '2026-12-31');
 *
 * Days after today are never processed (AttendanceService also skips them, and locked days).
 */
class AttendanceReprocessor
{
    /**
     * Employees employed at some point between $from and $to (also people who joined or left during it).
     * The visibility scope STAYS ON: on a screen, HR only reprocesses whom they can see. Jobs and commands
     * remove it themselves: ->withoutGlobalScope(VisibleToCurrentUser::class).
     *
     * @return Builder<Employee>
     */
    public function employedBetween(CarbonInterface|string $from, CarbonInterface|string $to): Builder
    {
        $from = $this->date($from);
        $to = $this->date($to);

        return Employee::query()
            ->whereDate('join_date', '<=', $to)
            ->where(fn (Builder $q) => $q->whereNull('separation_date')->orWhereDate('separation_date', '>=', $from));
    }

    /**
     * Same as employedBetween(), for code without a logged-in user (triggers, commands): everyone.
     *
     * @return Builder<Employee>
     */
    public function everyoneEmployedBetween(CarbonInterface|string $from, CarbonInterface|string $to): Builder
    {
        return $this->employedBetween($from, $to)->withoutGlobalScope(VisibleToCurrentUser::class);
    }

    /**
     * One job per employee. Jobs wait for the database transaction (e.g. an approval) to be committed,
     * so they never read data that is then rolled back.
     *
     * @param  iterable<int|string>  $employeeIds
     * @return int how many employees were queued (0 when the whole range is in the future)
     */
    public function queue(iterable $employeeIds, CarbonInterface|string $from, CarbonInterface|string $to, bool $sync = false): int
    {
        $from = $this->date($from);
        $to = min($this->date($to), $this->today());
        if ($from > $to) {
            return 0;
        }

        $ids = collect($employeeIds)->map(fn ($id) => (int) $id)->unique()->values();
        foreach ($ids as $id) {
            $job = (new ProcessAttendance($id, $from, $to))->afterCommit();
            $sync ? dispatch_sync($job) : dispatch($job);
        }

        return $ids->count();
    }

    /** Today in the COMPANY's timezone (Company Settings), as YYYY-MM-DD. */
    public function today(): string
    {
        return CarbonImmutable::now(settings('timezone', config('app.timezone')))->toDateString();
    }

    private function date(CarbonInterface|string $date): string
    {
        return $date instanceof CarbonInterface ? $date->toDateString() : CarbonImmutable::parse($date)->toDateString();
    }
}
