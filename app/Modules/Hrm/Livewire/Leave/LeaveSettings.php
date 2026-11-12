<?php

namespace App\Modules\Hrm\Livewire\Leave;

use App\Modules\Hrm\Data\LeaveYear;
use App\Modules\Hrm\Models\LeaveLedgerEntry;
use App\Modules\Hrm\Services\AccrueEarnedLeave;
use App\Modules\Hrm\Services\CarryForwardLeave;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Company leave settings, on the Leave types tab. [LV-03, LV-05]
 *
 *   startMonth      leave.year_start_month (8.2): locked once any leave balance exists (it would move every
 *                   ledger row into another year)
 *   earnFrom        leave.earn_from (8.5): the go-live date; the nightly accrual credits nothing that started
 *                   before it (the opening balances cover that). Empty = no limit.
 *   closeAfterDays  leave.close_after_days (8.5): the previous leave year is carried forward this many days
 *                   after it ended, so late approvals still land in it
 */
class LeaveSettings extends Component
{
    public int $startMonth = 1;

    public string $earnFrom = '';

    public int $closeAfterDays = CarryForwardLeave::DEFAULT_CLOSE_AFTER_DAYS;

    public function mount(): void
    {
        $this->startMonth = LeaveYear::startMonth();
        $this->earnFrom = (string) settings(AccrueEarnedLeave::EARN_FROM, '');
        $this->closeAfterDays = CarryForwardLeave::closeAfterDays();
    }

    public function save(): void
    {
        Gate::authorize('hrm.leave.setup');
        $this->validate([
            'startMonth' => ['required', 'integer', 'between:1,12'],
            'earnFrom' => ['nullable', 'date_format:Y-m-d'],
            'closeAfterDays' => ['required', 'integer', 'between:0,90'],
        ], attributes: ['startMonth' => __('start month'), 'earnFrom' => __('earn leave from'), 'closeAfterDays' => __('close after')]);

        if ($this->locked() && $this->startMonth !== LeaveYear::startMonth()) {
            $this->addError('startMonth', __('Leave balances already exist, so the leave year can no longer change.'));

            return;
        }

        settings()->setMany([
            LeaveYear::SETTING => $this->startMonth,
            AccrueEarnedLeave::EARN_FROM => $this->earnFrom ?: null,
            CarryForwardLeave::CLOSE_AFTER_DAYS => $this->closeAfterDays,
        ]);
        $this->dispatch('toast', message: __('Leave settings saved.'), variant: 'success');
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
