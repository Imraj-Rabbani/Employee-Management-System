<?php

namespace App\Modules\Workflow\Contracts;

/**
 * OPTIONAL extra for an Approvable: every approver enters a number when approving (7.7), stored on their
 * approval row (approvals.amount). E.g. overtime: level 1 "recommends 3 hours", the last level "approves 2.5".
 * The document reads the numbers back from its approvals, typically in onApproved().
 *
 *     public function approverAmountLabel(): string  { return __('Hours'); }
 *     public function approverAmountDefault(): float { return $this->recommendedHours() ?? $this->hours; }
 *     public function approverAmountMax(): float     { return 24; }
 */
interface AsksApproverForAmount
{
    /** The field label in the approval modal, e.g. "Hours". */
    public function approverAmountLabel(): string;

    /** What the field starts with: usually the previous level's number, else what was asked. */
    public function approverAmountDefault(): float;

    /** The highest number allowed (the lowest is 0). */
    public function approverAmountMax(): float;
}
