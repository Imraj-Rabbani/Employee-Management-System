<?php

namespace App\Modules\Hrm\Services;

use App\Modules\Hrm\Contracts\SalaryBenchmarks;

/** Fallback when the Payroll module is not there: nobody has a salary. */
class NoSalaryBenchmarks implements SalaryBenchmarks
{
    public function grossRange(string $column, int $id): array
    {
        return ['count' => 0, 'min' => null, 'max' => null, 'average' => null];
    }
}
