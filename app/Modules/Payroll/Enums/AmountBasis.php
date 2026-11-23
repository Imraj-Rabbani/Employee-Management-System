<?php

namespace App\Modules\Payroll\Enums;

use App\Core\Enums\HasOptions;

/** Where the amounts of a head-wise run come from (payroll_runs.amount_basis). Off-cycle runs always use Upload. */
enum AmountBasis: string
{
    use HasOptions;

    case HeadRule = 'head_rule';   // the head's own rule: fixed amount, % of basic or % of gross (e.g. festival bonus)
    case Salary = 'salary';        // the employee's amount for that head in their salary (annual / one-time heads)
    case Upload = 'upload';        // a file with an amount per employee (e.g. incentives)

    public function label(): string
    {
        return match ($this) {
            self::HeadRule => __('The head\'s rule'),
            self::Salary => __('The employee\'s salary'),
            self::Upload => __('Uploaded amounts'),
        };
    }

    public function hint(): string
    {
        return match ($this) {
            self::HeadRule => __('Fixed amount, or % of basic / gross, as set on the head. Everyone eligible in the scope.'),
            self::Salary => __('The amount of this head in each employee\'s salary (annual / one-time heads). Everyone eligible in the scope.'),
            self::Upload => __('Upload a file with an amount per employee on the run\'s page. Only those employees.'),
        };
    }

    /** Does the run take everyone eligible in its scope (true), or only the employees in the upload (false)? */
    public function usesScope(): bool
    {
        return $this !== self::Upload;
    }
}
