<?php

namespace App\Modules\Payroll\Livewire;

use App\Modules\Payroll\Enums\PayrollCalcStatus;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Models\PayrollRun;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Modal "payroll-run-progress": how far a run's calculation is, and which employees failed and why.
 * Open it with the event "show-payroll-run" {id}. While the run is Processing the view polls every 2 seconds
 * (wire:poll only exists then), so the bar moves by itself and stops when the run is Processed.
 *
 * Permission payroll.run.view; only runs the user may see (PayrollRun::visibleTo).
 */
class PayrollRunProgress extends Component
{
    /** Failed employees listed at most; the rest are counted. */
    private const MAX_FAILURES = 100;

    #[Locked]
    public ?int $runId = null;

    #[On('show-payroll-run')]
    public function show(int $id): void
    {
        Gate::authorize('payroll.run.view');
        $this->runId = PayrollRun::query()->visibleTo(Auth::user())->findOrFail($id)->id;

        Flux::modal('payroll-run-progress')->show();
    }

    public function render(): View
    {
        $run = $this->runId ? PayrollRun::query()->visibleTo(Auth::user())->with(['entity:id,name'])->find($this->runId) : null;
        $counts = collect();
        $failures = collect();
        $warned = 0;

        if ($run !== null) {
            $counts = $run->employees()->selectRaw('calc_status, COUNT(*) AS n')->groupBy('calc_status')->pluck('n', 'calc_status');
            $warned = $run->employees()->where('calc_status', PayrollCalcStatus::Ok->value)->whereNotNull('warnings')->count();
            $failures = $run->employees()->where('calc_status', PayrollCalcStatus::Failed->value)
                ->with(['employee' => fn ($q) => $q->withoutGlobalScopes()->select('id', 'code', 'first_name', 'last_name')])
                ->orderBy('id')->limit(self::MAX_FAILURES)->get();
        }

        $ok = (int) ($counts[PayrollCalcStatus::Ok->value] ?? 0);
        $failed = (int) ($counts[PayrollCalcStatus::Failed->value] ?? 0);
        $pending = (int) ($counts[PayrollCalcStatus::Pending->value] ?? 0);
        $total = $ok + $failed + $pending;

        return view('payroll::livewire.payroll-run-progress', [
            'run' => $run,
            'ok' => $ok,
            'failed' => $failed,
            'pending' => $pending,
            'total' => $total,
            'percent' => $total > 0 ? (int) floor(($ok + $failed) / $total * 100) : 0,
            'warned' => $warned,
            'failures' => $failures,
            'moreFailures' => max(0, $failed - self::MAX_FAILURES),
            'busy' => $run?->status === PayrollRunStatus::Processing,
        ]);
    }
}
