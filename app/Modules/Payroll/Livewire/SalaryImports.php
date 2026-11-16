<?php

namespace App\Modules\Payroll\Livewire;

use App\Core\Support\TenantUploads;
use App\Modules\Hrm\Enums\ImportStatus;
use App\Modules\Payroll\Imports\SalaryImporter;
use App\Modules\Payroll\Jobs\ImportSalaries;
use App\Modules\Payroll\Models\SalaryImport;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/** Payroll → Salary Setup → Import salaries: upload, follow the queued import, read the error report. [PC-03] */
class SalaryImports extends Component
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $file = null;

    public function start(TenantUploads $uploads): void
    {
        Gate::authorize('payroll.salary.manage');
        $this->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240']], attributes: ['file' => __('file')]);

        /** @var TemporaryUploadedFile $file */
        $file = $this->file;
        $import = SalaryImport::create([
            'user_id' => Auth::id(),
            'file_path' => $uploads->store($file, 'imports/salaries'),
            'file_name' => $file->getClientOriginalName(),
        ]);

        // Into the queue. With QUEUE_CONNECTION=sync (tests) it runs right here instead.
        ImportSalaries::dispatch($import->id);

        $this->reset('file');
        $this->dispatch('toast', message: __('File uploaded. The import runs in the background.'), variant: 'success');
    }

    public function render(): View
    {
        Gate::authorize('payroll.salary.manage');
        $imports = SalaryImport::query()->with('user')->latest('id')->limit(10)->get();

        $headings = array_map(fn (array $c) => rtrim($c[0], ' *'), SalaryImporter::COLUMNS);
        foreach (SalaryImporter::headColumns() as $key => $head) {
            $headings[$key] = SalaryImporter::headHeading($head);
        }

        return view('payroll::livewire.salary-imports', [
            'imports' => $imports,
            'busy' => $imports->contains(fn (SalaryImport $i) => ! $i->status->isFinished()),
            'headings' => $headings,
            'pending' => ImportStatus::Pending,
        ]);
    }
}
