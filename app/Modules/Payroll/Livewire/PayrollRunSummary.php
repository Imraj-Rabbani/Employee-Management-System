<?php

namespace App\Modules\Payroll\Livewire;

use App\Modules\Payroll\Actions\LockPayrollRun;
use App\Modules\Payroll\Actions\ProcessPayrollRun;
use App\Modules\Payroll\Actions\SubmitPayrollRun;
use App\Modules\Workflow\Exceptions\WorkflowException;
use App\Modules\Payroll\Enums\PayrollCalcStatus;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Services\PayrollReview;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The run page's header: what the run is, its totals against the previous period, the approval history, and the
 * actions its status allows (PayrollRunPolicy): Calculate / Edit / Submit for approval / Take back / Lock / Progress.
 * Polls while the run is processing; when the calculation ends it tells the register and the exception list
 * ("payroll-run-refreshed").
 *
 * View: app/Modules/Payroll/resources/views/livewire/payroll-run-summary.blade.php
 */
class PayrollRunSummary extends Component
{
    #[Locked]
    public int $runId;

    /** The status shown last time: a change from Processing means the numbers are new. */
    public string $lastStatus = '';

    public function mount(int $runId): void
    {
        $this->runId = $runId;
        $this->lastStatus = $this->run()->status->value;
    }

    public function calculate(ProcessPayrollRun $action): void
    {
        $run = $this->run();
        Gate::authorize('calculate', $run);
        if ($problem = $action->problem($run)) {
            $this->dispatch('toast', variant: 'error', message: $problem);

            return;
        }

        $action->handle($run);
        $this->dispatch('show-payroll-run', id: $run->id);
        $change = $action->scopeChange;
        if ($change['added'] + $change['removed'] > 0) {
            $this->dispatch('toast', variant: 'info', message: __('Employees brought up to date: :added added, :removed removed (no longer in scope).', $change));
        }

        // A sync queue (or a tiny run) may already be done: no Processing state for render() to notice.
        if ($run->fresh()->status !== PayrollRunStatus::Processing) {
            $this->lastStatus = $run->fresh()->status->value;
            $this->dispatch('payroll-run-refreshed');
        }
    }

    public function submit(SubmitPayrollRun $action): void
    {
        $run = $this->run();
        Gate::authorize('submit', $run);
        if ($problem = $action->problem($run)) {
            $this->dispatch('toast', variant: 'error', message: $problem);

            return;
        }

        try {
            $action->handle($run);
        } catch (WorkflowException $e) {
            $this->dispatch('toast', variant: 'error', message: $e->getMessage());

            return;
        }
        $this->dispatch('payroll-run-refreshed');
        $this->dispatch('toast', variant: 'success', message: __(':code sent for approval. Its numbers can no longer change.', ['code' => $run->code]));
    }

    public function withdraw(SubmitPayrollRun $action): void
    {
        $run = $this->run();
        Gate::authorize('withdraw', $run);
        $action->withdraw($run);
        $this->dispatch('payroll-run-refreshed');
        $this->dispatch('toast', variant: 'success', message: __(':code taken back: it can be changed again.', ['code' => $run->code]));
    }

    public function lock(LockPayrollRun $action): void
    {
        $run = $this->run();
        Gate::authorize('lock', $run);
        $action->handle($run);
        $this->dispatch('payroll-run-refreshed');
        $this->dispatch('toast', variant: 'success', message: __(':code locked. Changes for this period are paid as arrears from now on.', ['code' => $run->code]));
    }

    #[On('payroll-run-saved')]
    #[On('payroll-run-refreshed')]
    public function refresh(): void
    {
        // Re-rendering is enough (pay date / note changed, or employees calculated again: new totals).
    }

    private function run(): PayrollRun
    {
        return PayrollRun::query()->visibleTo(Auth::user())->with(['entity', 'head:id,name'])->findOrFail($this->runId);
    }

    public function render(PayrollReview $review): View
    {
        $run = $this->run();
        if ($this->lastStatus !== $run->status->value) {
            if ($this->lastStatus === PayrollRunStatus::Processing->value) {
                $this->dispatch('payroll-run-refreshed');
            }
            $this->lastStatus = $run->status->value;
        }

        $previous = $review->previousRun($run);
        $counts = $run->employees()->selectRaw('calc_status, COUNT(*) AS n')->groupBy('calc_status')->pluck('n', 'calc_status');

        return view('payroll::livewire.payroll-run-summary', [
            'run' => $run,
            'previous' => $previous,
            'gross' => PayrollReview::change($run->total_gross, $previous?->total_gross),
            'net' => PayrollReview::change($run->total_net, $previous?->total_net),
            'failed' => (int) ($counts[PayrollCalcStatus::Failed->value] ?? 0),
            'pending' => (int) ($counts[PayrollCalcStatus::Pending->value] ?? 0),
            'busy' => $run->status === PayrollRunStatus::Processing,
        ]);
    }
}
