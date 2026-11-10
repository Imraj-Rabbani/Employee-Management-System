<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Modules\Payroll\Actions\StorePunches;
use App\Modules\Payroll\Enums\PunchSource;
use App\Modules\Payroll\Imports\PunchFileReader;
use App\Modules\Payroll\Models\PunchLog;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * "Upload punches" (CSV / Excel, 7.3c) and "Match unmatched punches" on the Punches tab. [TA-04]
 * The whole file is checked first; one problem = nothing saved. Punches already stored are skipped.
 */
class PunchUpload extends Component
{
    use WithFileUploads;

    public ?TemporaryUploadedFile $file = null;

    /** @var list<string> */
    public array $problems = [];

    public function upload(PunchFileReader $reader, StorePunches $store): void
    {
        Gate::authorize('payroll.punch.manage');
        $this->problems = [];
        $this->validate(['file' => ['required', 'file', 'mimes:csv,txt,xlsx,xls', 'max:10240']], attributes: ['file' => __('file')]);

        [$punches, $problems] = $reader->read($this->file->getRealPath());
        if ($problems !== []) {
            $this->problems = $problems;

            return;
        }

        $stored = $store->handle($punches, PunchSource::Csv, batch: (string) Str::uuid(), userId: Auth::id());
        $skipped = count($punches) - $stored;

        $this->reset('file');
        Flux::modal('punch-upload')->close();
        $this->dispatch('punches-saved');
        $this->dispatch('toast', variant: 'success', message: $skipped > 0
            ? __(':stored punches added, :skipped were already there.', ['stored' => $stored, 'skipped' => $skipped])
            : trans_choice(':count punch added.|:count punches added.', $stored));
    }

    /** After HR gave employees an Attendance ID: link the punches that were waiting for it. */
    public function linkUnmatched(): void
    {
        Gate::authorize('payroll.punch.manage');
        $linked = PunchLog::linkEmployees();

        $this->dispatch('punches-saved');
        $this->dispatch('toast', message: $linked > 0
            ? trans_choice(':count punch matched to an employee.|:count punches matched to employees.', $linked)
            : __('No punches could be matched: no employee has their Attendance ID (yet).'));
    }

    public function render(): View
    {
        return view('payroll::livewire.attendance.punch-upload');
    }
}
