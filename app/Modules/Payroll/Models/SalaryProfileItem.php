<?php

namespace App\Modules\Payroll\Models;

use App\Core\Casts\MoneyCast;
use App\Core\Models\TenantModel;
use App\Core\Support\Money;
use App\Modules\Payroll\Enums\ProfileValueType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One head in a salary profile with its value (legacy `salaryheadvalues`). [PC-02]
 * Written only through SalaryProfile::syncItems(), which logs ONE entry on the profile.
 *
 * @property int $id
 * @property int $salary_profile_id
 * @property int $salary_head_id
 * @property ProfileValueType $value_type
 * @property Money|null $amount for value_type Amount
 * @property string|null $rate percent for the "% of ..." value types, decimal as a string
 * @property-read SalaryHead $head
 */
class SalaryProfileItem extends TenantModel
{
    /**
     * No own history entries (Spatie's LogsActivity records only the events listed here): the profile gets one
     * readable entry per save.
     *
     * @var list<string>
     */
    protected static $recordEvents = [];

    protected $fillable = ['salary_profile_id', 'salary_head_id', 'value_type', 'amount', 'rate'];

    /** @return array<string, mixed> */
    protected function casts(): array
    {
        return [
            'value_type' => ProfileValueType::class,
            'amount' => MoneyCast::class,
            'rate' => 'decimal:4',
        ];
    }

    /** The number as typed in the form: "750.00" or "50.0000". */
    public function value(): string
    {
        return $this->value_type->usesRate() ? (string) $this->rate : (string) $this->amount;
    }

    /** "50% of basic", "750.00" */
    public function describe(): string
    {
        $rate = rtrim(rtrim((string) ($this->rate ?? '0'), '0'), '.') ?: '0';

        return match ($this->value_type) {
            ProfileValueType::Amount => ($this->amount ?? Money::zero())->format(),
            ProfileValueType::PercentOfBasic => __(':rate% of basic', ['rate' => $rate]),
            ProfileValueType::PercentOfGross => __(':rate% of gross', ['rate' => $rate]),
        };
    }

    /** @return BelongsTo<SalaryProfile, $this> */
    public function profile(): BelongsTo
    {
        return $this->belongsTo(SalaryProfile::class, 'salary_profile_id');
    }

    /** @return BelongsTo<SalaryHead, $this> */
    public function head(): BelongsTo
    {
        return $this->belongsTo(SalaryHead::class, 'salary_head_id');
    }
}
