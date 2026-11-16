<?php

namespace App\Modules\Hrm\Contracts;

/**
 * What employees in the same grade / designation earn now (current monthly gross). The job offer compares its
 * salary with it (6.7, finished in 9.4). Salaries live in the Payroll module, which binds the real answer
 * (ComparableSalaries); without it NoSalaryBenchmarks says "no salaries known".
 */
interface SalaryBenchmarks
{
    /** Fewer people than this: no amounts, only the count (a range of 1–2 people shows their salaries). */
    public const MIN_PEOPLE = 3;

    /**
     * @param  'grade_id'|'designation_id'  $column
     * @return array{count: int, min: string|null, max: string|null, average: string|null} decimals as strings;
     *                                                                                     amounts NULL below MIN_PEOPLE
     */
    public function grossRange(string $column, int $id): array;
}
