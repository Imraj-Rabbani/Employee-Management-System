<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Modules\Payroll\Actions\RequestCorrection;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

/**
 * Self Service correction limits (company settings, 7.6), on the Corrections tab for attendance setup users. [TA-06]
 * 0 = no limit. HR's own corrections are never limited.
 */
class CorrectionSettings extends Component
{
    public int $daysBack = 0;

    public int $monthlyLimit = 0;

    public function mount(): void
    {
        $this->daysBack = (int) settings(RequestCorrection::DAYS_BACK, 0);
        $this->monthlyLimit = (int) settings(RequestCorrection::MONTHLY_LIMIT, 0);
    }

    public function save(): void
    {
        Gate::authorize('payroll.attendance.setup');
        $this->validate([
            'daysBack' => ['required', 'integer', 'min:0', 'max:366'],
            'monthlyLimit' => ['required', 'integer', 'min:0', 'max:31'],
        ], attributes: ['daysBack' => __('days back'), 'monthlyLimit' => __('per month')]);

        settings()->setMany([RequestCorrection::DAYS_BACK => $this->daysBack, RequestCorrection::MONTHLY_LIMIT => $this->monthlyLimit]);

        $this->dispatch('toast', message: __('Correction limits saved.'), variant: 'success');
    }

    public function render(): View
    {
        return view('payroll::livewire.attendance.correction-settings');
    }
}
