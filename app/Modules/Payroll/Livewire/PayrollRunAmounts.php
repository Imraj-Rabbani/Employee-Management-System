<?php

namespace App\Modules\Payroll\Livewire;

use App\Core\Support\Money;
use App\Modules\Payroll\Imports\RunAmountImporter;
use App\Modules\Payroll\Models\PayrollRun;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * The run page's "Uploaded amounts" card (upload runs only): how many amounts, their total, and Upload (modal
 * "run-amounts-upload": all rows or none, problems listed per row). After an upload: calculate the run.
 *
 * View: app/Modules/Payroll/resources/views/livewire/payroll-run-amounts.blade.php
 */
class PayrollRunAmounts extends Component
{
    use WithFileUploads;

    #[Locked]
    public int $runId;

    public ?TemporaryUploadedFile $file = null;

    /** @var list<array{row: int, column: string, message: string}> */
    public array $problems = [];

    public function mount(int $runId): void
    {
        $this->runId = $runId;
    }

    #[On('payroll-run-refreshed')]
    public function refreshed(): void {}

    public function upload(RunAmountImporter $importer): void
    {
        $run = $this->run();
        Gate::authorize('update', $run);
        $this->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv,txt', 'max:10240']], attributes: ['file' => __('file')]);

        /** @var TemporaryUploadedFile $file */
        $file = $this->file;
        $count = $importer->run($file->getRealPath(), $run, Auth::user());
        if ($count === null) {
            $this->problems = $importer->errors();

            return;
        }

        $this->reset('file', 'problems');
        Flux::modal('run-amounts-upload')->close();
        $this->dispatch('payroll-run-refreshed');
        $this->dispatch('toast', variant: 'success', message: trans_choice(':count amount uploaded. Calculate the run now.|:count amounts uploaded. Calculate the run now.', $count));
    }

    private function run(): PayrollRun
    {
        return PayrollRun::query()->visibleTo(Auth::user())->with('head:id,name,code')->findOrFail($this->runId);
    }

    public function render(): View
    {
        $run = $this->run();
        $sums = $run->amounts()->selectRaw('COUNT(*) AS n, COUNT(DISTINCT employee_id) AS people, COALESCE(SUM(amount), 0) AS total')->toBase()->first();

        return view('payroll::livewire.payroll-run-amounts', [
            'run' => $run,
            'count' => (int) $sums->n,
            'people' => (int) $sums->people,
            'total' => Money::of((string) $sums->total),
            'columns' => RunAmountImporter::COLUMNS,
        ]);
    }
}
