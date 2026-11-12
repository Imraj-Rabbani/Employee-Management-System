<?php

namespace App\Modules\Hrm\Data;

/**
 * What LeaveRules decided about one request: how many days it costs and what is wrong with it.
 *
 *     $check->ok()                 no problems
 *     $check->days                 days counted (what the ledger will take): 2.5
 *     $check->dates                ['2026-12-01' => 1.0, '2026-12-02' => 0.5 …] the counted days
 *     $check->bridgedDays          days off added by the sandwich rule (also in $dates)
 *     $check->has('balance')       a problem with this code
 *     $check->messages()           for a toast / error list
 */
final readonly class LeaveCheck
{
    /**
     * @param  array<string, float>  $dates
     * @param  list<LeaveProblem>  $problems
     */
    public function __construct(
        public float $days,
        public array $dates,
        public int $calendarDays,
        public int $bridgedDays,
        public array $problems,
    ) {}

    public function ok(): bool
    {
        return $this->problems === [];
    }

    public function has(string $code): bool
    {
        foreach ($this->problems as $problem) {
            if ($problem->code === $code) {
                return true;
            }
        }

        return false;
    }

    /** @return list<string> the problem codes, in the order found */
    public function codes(): array
    {
        return array_map(fn (LeaveProblem $p) => $p->code, $this->problems);
    }

    /** @return list<string> */
    public function messages(): array
    {
        return array_map(fn (LeaveProblem $p) => $p->message, $this->problems);
    }
}
