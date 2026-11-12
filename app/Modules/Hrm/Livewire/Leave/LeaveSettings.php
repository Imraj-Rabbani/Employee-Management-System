<?php

namespace App\Modules\Hrm\Livewire\Leave;

use App\Modules\Hrm\Data\LeaveYear;
use App\Modules\Hrm\Models\LeaveLedgerEntry;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Company setting "Leave year starts in" (leave.year_start_month), on the Leave types tab. [LV-03]
 * Locked once any leave balance exists: changing it would move every ledger row into another year.
 */
class LeaveSettings extends Component
{
    public int $startMonth = 1;

    public function mount(): void
    {
        $this->startMonth = LeaveYear::startMonth();
    }

    public function save(): void
    {
        Gate::authorize('hrm.leave.setup');
        $this->validate(['startMonth' => ['required', 'integer', 'between:1,12']], attributes: ['startMonth' => __('start month')]);

        if ($this->locked() && $this->startMonth !== LeaveYear::startMonth()) {
            $this->addError('startMonth', __('Leave balances already exist, so the leave year can no longer change.'));

            return;
        }

        settings()->set(LeaveYear::SETTING, $this->startMonth);
        $this->dispatch('toast', message: __('Leave year saved.'), variant: 'success');
    }

    private function locked(): bool
    {
        return LeaveLedgerEntry::query()->exists();
    }

    public function render(): View
    {
        return view('hrm::livewire.leave.leave-settings', [
            'months' => collect(range(1, 12))->mapWithKeys(fn (int $m) => [$m => now()->month($m)->startOfMonth()->translatedFormat('F')])->all(),
            'locked' => $this->locked(),
            'current' => LeaveYear::current(),
        ]);
    }
}
