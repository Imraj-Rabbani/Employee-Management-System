<?php

namespace App\Modules\Payroll\Exceptions;

use LogicException;

/**
 * Something tried to change a payroll run's numbers after it was submitted (pending / approved / locked /
 * disbursed). A bug, not a user error: the screens never offer it (PayrollRunPolicy), the guards below refuse it.
 */
class PayrollLockedException extends LogicException
{
    public static function for(string $code, string $status): self
    {
        return new self("Payroll run {$code} is {$status}: its numbers cannot change.");
    }
}
