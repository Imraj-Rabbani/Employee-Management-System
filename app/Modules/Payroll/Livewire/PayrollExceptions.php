<?php

namespace App\Modules\Payroll\Livewire;

use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Services\PayrollReview;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The run page's exception list (PR-04): failures, warnings, big changes against the previous period, zero / negative
 * net, joiners and leavers, holds, missing bank accounts (PayrollReview::exceptions). Click a name for the breakdown.
 *
 * View: app/Modules/Payroll/resources/views/livewire/payroll-exceptions.blade.php
 */
class PayrollExceptions extends Component
{
    /** Rows listed per group; the rest are counted (the register's filter shows them all). */
    public const PER_GROUP = 50;

    #[Locked]
    public int $runId;

    public function mount(int $runId): void
    {
        $this->runId = $runId;
    }

    #[On('payroll-run-refreshed')]
    public function refreshed(): void {}

    public function render(PayrollReview $review): View
    {
        $run = PayrollRun::query()->visibleTo(Auth::user())->findOrFail($this->runId);

        return view('payroll::livewire.payroll-exceptions', [
            'groups' => $review->exceptions($run),
            'perGroup' => self::PER_GROUP,
        ]);
    }
}
