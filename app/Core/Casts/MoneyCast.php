<?php

namespace App\Core\Casts;

use App\Core\Support\Money;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * Turns a DECIMAL(15,2) column into a Money object and back:  protected $casts = ['basic' => MoneyCast::class];
 * Then $employee->basic->percent(40) works, and $employee->basic = Money::of('50000') saves "50000.00".
 *
 * @implements CastsAttributes<Money, Money|string|int|null>
 */
class MoneyCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): ?Money
    {
        return $value === null ? null : Money::of((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        if ($value === null) {
            return null;
        }

        return ($value instanceof Money ? $value : Money::of($value))->toDecimal();
    }
}
