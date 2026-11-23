<?php

namespace App\Modules\Payroll\Stages;

use App\Core\Support\Money;
use App\Modules\Payroll\Data\PayrollContext;
use App\Modules\Payroll\Data\PayrollLine;
use App\Modules\Payroll\Enums\DayType;
use App\Modules\Payroll\Enums\HeadRole;
use App\Modules\Payroll\Enums\PayrollItemSource;
use App\Modules\Payroll\Models\AttendanceDay;
use App\Modules\Payroll\Models\EmployeeSalary;
use App\Modules\Payroll\Services\PayrollSettings;
use Closure;

/**
 * Stage 5. Approved overtime of the days the employee works in the period, paid into the head mapped to
 * Overtime in the payroll settings (legacy headOvertime):
 *
 *   hourly rate  = monthly basic ÷ hours per month (setting, 208)          the FULL basic, not the prorated one
 *   amount       = Σ per day type (approved hours × hourly rate × multiplier of that day type)
 *
 * Approved hours = attendance_days.ot_approved_minutes, which approving an overtime request fills (7.7).
 * Worked overtime that nobody asked for / approved is not paid. Worked out in one go and rounded once
 * (basic × Σ minutes × multiplier ÷ (divisor × 60)), so a month of small days adds no rounding cents.
 *
 * No Overtime head in the settings → nothing is paid; approved hours then give a warning.
 */
class Overtime
{
    public function __construct(private readonly PayrollSettings $settings) {}

    public function handle(PayrollContext $ctx, Closure $next): mixed
    {
        $minutes = AttendanceDay::query()
            ->where('employee_id', $ctx->employee->id)
            ->whereBetween('date', [$ctx->workFrom->toDateString(), $ctx->workTo->toDateString()])
            ->where('ot_approved_minutes', '>', 0)
            ->selectRaw('day_type, SUM(ot_approved_minutes) AS minutes')
            ->groupBy('day_type')
            ->pluck('minutes', 'day_type');

        $total = (int) $minutes->sum();
        if ($total === 0) {
            return $next($ctx);
        }
        $ctx->overtimeHours = round($total / 60, 2);

        $head = $this->settings->head(HeadRole::Overtime);
        if ($head === null) {
            $ctx->warn(__(':hours h approved overtime not paid: no head is set for Overtime in the payroll settings.', ['hours' => PayrollContext::days($ctx->overtimeHours)]));
            $ctx->overtimeHours = 0.0;

            return $next($ctx);
        }

        $basic = $ctx->salary->first(fn (EmployeeSalary $row) => $row->head->is_basic)->amount;

        $weighted = '0';   // Σ minutes × multiplier
        foreach ($minutes as $type => $sum) {
            $multiplier = $this->settings->overtimeMultiplier(DayType::from((int) $type));
            $weighted = bcadd($weighted, bcmul((string) (int) $sum, $multiplier, 4), 4);
        }
        $amount = $basic->multiply($weighted)->divide($this->settings->overtimeDivisor() * 60);

        if ($amount->isPositive()) {
            $ctx->lines[] = new PayrollLine($head, PayrollItemSource::Overtime, $amount, $amount, Money::zero());
        }

        return $next($ctx);
    }
}
