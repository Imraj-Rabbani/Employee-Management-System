<?php

namespace App\Modules\Payroll\Models;

use App\Core\Casts\MoneyCast;
use App\Core\Concerns\HasEffectiveDates;
use App\Core\Models\TenantModel;
use App\Core\Support\Money;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Data\SalaryBreakdown;
use App\Modules\Payroll\Data\SalaryLine;
use App\Modules\Payroll\Enums\ProfileValueType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Collection;

/**
 * One head of an employee's salary, valid from effective_from to effective_to (legacy `salaries`). [PC-03]
 * A salary = the rows of one employee with the same effective_from. Written only through Actions\SetEmployeeSalary,
 * which logs ONE readable entry on the employee (the rows log nothing themselves).
 *
 *     EmployeeSalary::where('employee_id', $id)->effectiveOn('2026-12-01')->with('head')->get()
 *     EmployeeSalary::breakdown($rows)->gross
 *
 * @property int $id
 * @property int $employee_id
 * @property int $salary_head_id
 * @property ProfileValueType|null $value_type
 * @property string|null $rate
 * @property Money $amount
 * @property int|null $salary_profile_id
 * @property-read SalaryHead $head
 */
class EmployeeSalary extends TenantModel
{
    use HasEffectiveDates;

    /** @var list<string> no own history entries: the employee gets one readable entry per salary change */
    protected static $recordEvents = [];

    protected $fillable = ['employee_id', 'salary_head_id', 'value_type', 'rate', 'amount', 'effective_from', 'effective_to', 'salary_profile_id'];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return ['value_type' => ProfileValueType::class, 'rate' => 'decimal:4', 'amount' => MoneyCast::class];
    }

    /** "50% of basic", or "" for the basic / a typed amount. */
    public function describe(): string
    {
        if ($this->value_type === null) {
            return '';
        }
        $rate = rtrim(rtrim((string) $this->rate, '0'), '.') ?: '0';

        return $this->value_type === ProfileValueType::PercentOfGross
            ? __(':rate% of gross', ['rate' => $rate])
            : __(':rate% of basic', ['rate' => $rate]);
    }

    /**
     * Totals of a set of rows (with their head loaded).
     *
     * @param  iterable<self>  $rows
     */
    public static function breakdown(iterable $rows): SalaryBreakdown
    {
        $lines = [];
        foreach ($rows as $row) {
            $lines[] = new SalaryLine($row->head, $row->describe(), $row->amount, $row->value_type, $row->rate);
        }

        return SalaryBreakdown::fromLines($lines);
    }

    /**
     * Every salary of the employee, newest first: effective_from 'Y-m-d' => its rows (head loaded).
     *
     * @return Collection<string, \Illuminate\Database\Eloquent\Collection<int, self>>
     */
    public static function historyOf(Employee $employee): Collection
    {
        return self::where('employee_id', $employee->id)->with(['head', 'profile'])->latestFirst()->get()
            ->groupBy(fn (self $row) => $row->effective_from->toDateString());
    }

    /** @return BelongsTo<Employee, $this> */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class);
    }

    /** @return BelongsTo<SalaryHead, $this> */
    public function head(): BelongsTo
    {
        return $this->belongsTo(SalaryHead::class, 'salary_head_id');
    }

    /** @return BelongsTo<SalaryProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(SalaryProfile::class, 'salary_profile_id');
    }
}
