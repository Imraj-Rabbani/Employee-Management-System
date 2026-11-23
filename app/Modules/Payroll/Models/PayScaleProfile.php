<?php

namespace App\Modules\Payroll\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Database\Factories\PayScaleProfileFactory;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * How payroll treats a group of employees (legacy `payscaleprofiles`).
 * The profile of an employee: PayrollSettings::payScaleFor($employee) (their own, else the company default).
 *
 * @property int $id
 * @property string $code
 * @property string $name
 * @property bool $pays_arrear back-dated salary changes and late joiners are paid as arrears
 * @property int $threshold_day joined AFTER this day of the month → first paid in the next month's run; 0 = no threshold
 * @property bool $deduct_lwp leave without pay reduces the salary
 * @property bool $ignore_late_lwp LWP that comes from late attendance is not deducted
 * @property bool $deduct_absent absent days (attendance) reduce the salary; a half day counts 0.5
 * @property string|null $detail
 * @property bool $is_active
 */
#[UseFactory(PayScaleProfileFactory::class)]
class PayScaleProfile extends TenantModel
{
    /** @use HasFactory<PayScaleProfileFactory> */
    use HasFactory;

    protected $fillable = ['code', 'name', 'pays_arrear', 'threshold_day', 'deduct_lwp', 'ignore_late_lwp', 'deduct_absent', 'detail', 'is_active'];

    protected $attributes = ['pays_arrear' => true, 'threshold_day' => 0, 'deduct_lwp' => true, 'ignore_late_lwp' => false, 'deduct_absent' => true, 'is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'pays_arrear' => 'boolean',
            'threshold_day' => 'integer',
            'deduct_lwp' => 'boolean',
            'ignore_late_lwp' => 'boolean',
            'deduct_absent' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** "Arrears · threshold day 15 · LWP deducted (not from lates) · absence deducted", for the list. */
    public function summary(): string
    {
        return collect([
            $this->pays_arrear ? __('Arrears paid') : __('No arrears'),
            $this->threshold_day > 0 ? __('threshold day :day', ['day' => $this->threshold_day]) : __('no threshold'),
            match (true) {
                ! $this->deduct_lwp => __('LWP not deducted'),
                $this->ignore_late_lwp => __('LWP deducted (not from lates)'),
                default => __('LWP deducted'),
            },
            $this->deduct_absent ? __('absence deducted') : __('absence not deducted'),
        ])->implode(' · ');
    }

    /** @return HasMany<Employee, $this> */
    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
