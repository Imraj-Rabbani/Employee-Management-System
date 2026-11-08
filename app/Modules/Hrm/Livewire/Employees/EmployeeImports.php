<?php

namespace App\Modules\Hrm\Livewire\Employees;

use App\Core\Support\TenantUploads;
use App\Modules\Hrm\Enums\ImportStatus;
use App\Modules\Hrm\Imports\EmployeeImporter;
use App\Modules\Hrm\Jobs\ImportEmployees;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmployeeImport;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * HRM → Employees → Import: upload an Excel file, follow the queued import, read the error report. [EMP-06]
 */
class EmployeeImports extends Component
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $file = null;

    public function start(TenantUploads $uploads): void
    {
        Gate::authorize('create', Employee::class);
        $this->validate(['file' => ['required', 'file', 'mimes:xlsx,xls,csv', 'max:10240']], attributes: ['file' => __('file')]);

        /** @var TemporaryUploadedFile $file */
        $file = $this->file;
        $import = EmployeeImport::create([
            'user_id' => Auth::id(),
            'file_path' => $uploads->store($file, 'imports/employees'),
            'file_name' => $file->getClientOriginalName(),
        ]);

        // Into the queue. With QUEUE_CONNECTION=sync (tests) it runs right here instead.
        ImportEmployees::dispatch($import->id);

        $this->reset('file');
        $this->dispatch('toast', message: __('File uploaded. The import runs in the background.'), variant: 'success');
    }

    public function render(): View
    {
        Gate::authorize('create', Employee::class);
        $imports = EmployeeImport::query()->with('user')->latest('id')->limit(10)->get();

        return view('hrm::livewire.employees.employee-imports', [
            'imports' => $imports,
            // Keep polling while something is still waiting or running.
            'busy' => $imports->contains(fn (EmployeeImport $i) => ! $i->status->isFinished()),
            'headings' => array_map(fn (array $c) => rtrim($c[0], ' *'), EmployeeImporter::COLUMNS),
            'pending' => ImportStatus::Pending,
        ]);
    }
}
