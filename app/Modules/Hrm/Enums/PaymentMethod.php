<?php

namespace App\Modules\Hrm\Enums;

use App\Core\Enums\HasOptions;

/**
 * How an employee is paid (legacy `PaymentMethods`). Bank → needs a bank account (5.5), used by disbursement (12.1).
 * Backed by the SAME integers as legacy, so migrated data (Phase 15) keeps its values.
 * Legacy 0 = "None" is not a case: store NULL instead.
 *
 *     PaymentMethod::from(1)          PaymentMethod::Cash
 *     $employee->payment_method->label()   (with a model cast: 'payment_method' => PaymentMethod::class)
 */
enum PaymentMethod: int
{
    use HasOptions;

    case Cash = 1;
    case Bank = 2;

    public function label(): string
    {
        return match ($this) {
            self::Cash => __('Cash'),
            self::Bank => __('Bank'),
        };
    }
}
