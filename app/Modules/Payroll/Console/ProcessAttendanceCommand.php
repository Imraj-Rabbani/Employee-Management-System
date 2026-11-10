<?php

namespace App\Modules\Payroll\Console;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Payroll\Jobs\ProcessAttendance;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

/**
 * Queues attendance processing for the current company (one ProcessAttendance job per employee). [TA-05]
 *
 *     php artisan tenants:run payroll:process-attendance                     yesterday (the 01:00 schedule)
 *     php artisan tenants:run "payroll:process-attendance --from=2026-12-01 --to=2026-12-31"
 *     php artisan tenants:run "payroll:process-attendance --date=2026-12-03 --employee=5 --sync"
 *
 * "Yesterday" is in the COMPANY's timezone (Company Settings), not the server's.
 */
class ProcessAttendanceCommand extends Command
{
    protected $signature = 'payroll:process-attendance
                            {--date= : One day (YYYY-MM-DD); default yesterday}
                            {--from= : First day of a range}
                            {--to= : Last day of a range}
                            {--employee=* : Only these employee ids}
                            {--sync : Run now instead of queueing (small ranges, debugging)}';

    protected $description = 'Process attendance (punches → attendance days) for the current company';

    public function handle(): int
    {
        if (! tenant()) {
            $this->components->error('No company selected. Run it as: php artisan tenants:run payroll:process-attendance');

            return self::FAILURE;
        }
        if (! tenant()->hasModule('payroll')) {
            return self::SUCCESS;
        }

        $yesterday = CarbonImmutable::now(settings('timezone', config('app.timezone')))->subDay()->toDateString();
        $from = (string) ($this->option('from') ?? $this->option('date') ?? $yesterday);
        $to = (string) ($this->option('to') ?? $this->option('date') ?? $from);
        if (! $this->isDate($from) || ! $this->isDate($to) || $from > $to) {
            $this->components->error('Dates must be YYYY-MM-DD, and --from not after --to.');

            return self::FAILURE;
        }

        // Everyone employed at some point in the range (also people who left during it).
        $ids = Employee::withoutGlobalScope(VisibleToCurrentUser::class)
            ->whereDate('join_date', '<=', $to)
            ->where(fn ($q) => $q->whereNull('separation_date')->orWhereDate('separation_date', '>=', $from))
            ->when($this->option('employee'), fn ($q, $ids) => $q->whereIn('id', $ids))
            ->pluck('id');

        foreach ($ids as $id) {
            $job = new ProcessAttendance($id, $from, $to);
            $this->option('sync') ? dispatch_sync($job) : dispatch($job);
        }

        $this->components->info(($this->option('sync') ? 'Processed' : 'Queued')." {$ids->count()} employees, {$from} – {$to}.");

        return self::SUCCESS;
    }

    private function isDate(string $value): bool
    {
        return (bool) preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) && strtotime($value) !== false;
    }
}
