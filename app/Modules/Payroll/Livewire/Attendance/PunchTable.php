<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Core\Exports\ArrayExport;
use App\Modules\Hrm\Services\EmployeeVisibility;
use App\Modules\Payroll\Enums\PunchSource;
use App\Modules\Payroll\Models\PunchLog;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Payroll → Attendance → Punches: every punch, newest first.
 * Punches of employees the user can see; unmatched punches (no employee has that code) only for users
 * without an entity limit, since nobody can tell whose they are.
 *
 * View: app/Modules/Payroll/resources/views/livewire/attendance/punch-table.blade.php
 */
class PunchTable extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: '')]
    public string $sortBy = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    /** Filter "Source": '' = all */
    #[Url(as: 'source', except: '')]
    public string $source = '';

    /** Filter "Employee": '' = all */
    #[Url(as: 'matched', except: '')]
    public string $matched = '';

    #[Url(as: 'per_page', except: 10)]
    public int $perPage = 10;

    /** Columns the user may sort by. Anything else sent from the browser is ignored. */
    private const SORTABLE = ['punched_at', 'access_code', 'source'];

    /** @var array<string, string> */
    protected $listeners = ['punches-saved' => '$refresh'];

    /** Filter form → Apply: the new filter values came with this click; start again at page 1. */
    public function applyFilters(): void
    {
        $this->resetPage();
        Flux::modal('punch-filters')->close();
    }

    public function sort(string $field): void
    {
        if (! in_array($field, self::SORTABLE, true)) {
            return;
        }

        // Clicking the same column again flips the direction.
        $this->sortDir = $this->sortBy === $field && $this->sortDir === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $field;
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'source', 'matched');
        $this->resetPage();
        Flux::modal('punch-filters')->close();
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (PunchLog $punchLog) => [
            format_datetime($punchLog->punched_at),
            $punchLog->employee ? $punchLog->employee->code.' · '.$punchLog->employee->name : __('Unmatched'),
            $punchLog->access_code,
            $punchLog->source->label(),
            $punchLog->device->name ?? '—',
            $punchLog->distance_m === null ? '—' : __(':m m', ['m' => number_format($punchLog->distance_m)]),
        ])->all();

        return Excel::download(
            new ArrayExport([__('Time'), __('Employee'), __('Attendance ID'), __('Source'), __('Device'), __('Distance')], $rows),
            'punch-table-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** Typing a search or picking a filter or page size starts again at page 1. */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'source', 'matched', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /** @return Builder<PunchLog> */
    private function query(): Builder
    {
        $seesAll = app(EmployeeVisibility::class)->entityIdsFor(Auth::user()) === null;

        // whereHas('employee') goes through Employee's visibility scope: only employees this user may see.
        return PunchLog::query()
            ->with(['employee:id,code,first_name,last_name', 'device:id,name'])
            ->where(fn (Builder $q) => $q->whereHas('employee')->when($seesAll, fn (Builder $q) => $q->orWhereNull('employee_id')));
    }

    /** @return array<int|string, string> choices of the "Source" filter */
    private function sourceOptions(): array
    {
        return PunchSource::options();
    }

    /** @return array<int|string, string> choices of the "Employee" filter */
    private function matchedOptions(): array
    {
        return ['yes' => __('Matched'), 'no' => __('Unmatched')];
    }

    /** @return Builder<PunchLog> */
    private function filteredQuery(): Builder
    {
        $query = $this->query();

        if (trim($this->search) !== '') {
            // addcslashes: a search for "50%" means the text "50%", not "50 followed by anything".
            $like = '%'.addcslashes(trim($this->search), '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('access_code', 'like', $like));
        }

        if ($this->source !== '' && array_key_exists($this->source, $this->sourceOptions())) {
            $query->where('source', $this->source);
        }

        if ($this->matched !== '' && array_key_exists($this->matched, $this->matchedOptions())) {
            $this->matched === 'yes' ? $query->whereNotNull('employee_id') : $query->whereNull('employee_id');
        }

        if (in_array($this->sortBy, self::SORTABLE, true)) {
            $query->orderBy($this->sortBy, $this->sortDir === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('punched_at', 'desc');
        }

        return $query;
    }

    public function render(): View
    {
        // A page size typed into the URL that is not offered falls back to 10.
        $perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 10;

        return view('payroll::livewire.attendance.punch-table', [
            'rows' => $this->filteredQuery()->paginate($perPage),
            'sourceOptions' => $this->sourceOptions(),
            'matchedOptions' => $this->matchedOptions(),
        ]);
    }
}
