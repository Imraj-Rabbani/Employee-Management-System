<?php

namespace App\Modules\Payroll\Services;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Enums\HeadRole;
use App\Modules\Payroll\Models\PayScaleProfile;
use App\Modules\Payroll\Models\SalaryHead;

/**
 * The company's payroll settings, kept in the settings table (Payroll → Salary Setup → Settings). [PC-04, PC-05]
 *
 *     $settings->head(HeadRole::Overtime)       the SalaryHead mapped to overtime, or null
 *     $settings->payScaleFor($employee)          their pay scale profile, else the company default, else null
 */
class PayrollSettings
{
    /** [role value => salary_head_id] */
    public const HEADS = 'payroll.heads';

    public const DEFAULT_PAY_SCALE = 'payroll.default_pay_scale_profile_id';

    /** @return array<string, int> role value => salary_head_id, only roles that are mapped */
    public function headIds(): array
    {
        $map = settings(self::HEADS, []);

        return is_array($map) ? array_map('intval', array_filter($map, fn ($id) => $id !== null && $id !== '')) : [];
    }

    public function head(HeadRole $role): ?SalaryHead
    {
        $id = $this->headIds()[$role->value] ?? null;

        return $id ? SalaryHead::find($id) : null;
    }

    /**
     * The roles a head is mapped to (a mapped head cannot be deleted).
     *
     * @return list<HeadRole>
     */
    public function rolesOf(SalaryHead $head): array
    {
        return array_map(
            fn (string $role) => HeadRole::from($role),
            array_keys(array_filter($this->headIds(), fn (int $id) => $id === $head->id)),
        );
    }

    public function defaultPayScale(): ?PayScaleProfile
    {
        $id = settings(self::DEFAULT_PAY_SCALE);

        return $id ? PayScaleProfile::find($id) : null;
    }

    public function payScaleFor(Employee $employee): ?PayScaleProfile
    {
        return $employee->pay_scale_profile_id ? PayScaleProfile::find($employee->pay_scale_profile_id) : $this->defaultPayScale();
    }
}
