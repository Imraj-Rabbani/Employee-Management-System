<?php

namespace App\Modules\Payroll\Livewire;

use App\Modules\Payroll\Imports\AdjustmentImporter;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Payroll → Adjustments → Import (modal "adjustment-import"): upload a filled template; every row is checked,
 * then all are saved and sent for approval, or none (the problems are listed per row).
 */
class AdjustmentImport extends Component
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $file = null;

    /** @var list<array{row: int, column: string, message: string}> */
    public array $problems = [];

    #[On('import-adjustments')]
    public function open(): void
    {
        Gate::authorize('payroll.adjustment.manage');
        $this->reset('file', 'problems');
        $this->resetValidation();
        Flux::modal('adjustment-import')->show();
    }

    public function import(AdjustmentImporter $importer): void
    {
        Gate::authorize('payroll.adjustment.manage');
        $this->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:5120']], attributes: ['file' => __('file')]);

        /** @var TemporaryUploadedFile $file */
        $file = $this->file;
        $created = $importer->run($file->getRealPath(), Auth::user());

        if ($created === null) {
            $this->problems = $importer->errors();

            return;
        }

        $this->reset('file', 'problems');
        Flux::modal('adjustment-import')->close();
        $this->dispatch('adjustment-saved');
        $this->dispatch('toast', variant: 'success', message: trans_choice(':count adjustment sent for approval.|:count adjustments sent for approval.', $created, ['count' => $created]));
    }

    public function render(): View
    {
        return view('payroll::livewire.adjustment-import', ['columns' => AdjustmentImporter::COLUMNS]);
    }
}
