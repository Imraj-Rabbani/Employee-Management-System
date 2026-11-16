<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Core\Exports\ArrayExport;
use App\Modules\Payroll\Models\Roster;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Payroll → Attendance → Rosters: the list, newest first.
 * Only rosters of the org units the user can see (Roster::visibleTo). Click the number to open the roster.
 *
 * View: app/Modules/Payroll/resources/views/livewire/attendance/roster-table.blade.php
 */
class RosterTable extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: '')]
    public string $sortBy = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    #[Url(as: 'per_page', except: 10)]
    public int $perPage = 10;

    /** Columns the user may sort by. Anything else sent from the browser is ignored. */
    private const SORTABLE = ['id', 'title', 'from_date', 'to_date', 'employees_count'];

    /** @var array<string, string> */
    protected $listeners = ['roster-saved' => '$refresh'];

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
        $this->reset('search');
        $this->resetPage();
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (Roster $roster) => [
            $roster->number(),
            $roster->title,
            $roster->orgUnit->name ?? '-',
            format_date($roster->from_date),
            format_date($roster->to_date),
            $roster->getAttribute('employees_count'),
            $roster->statusBadge()[0],
        ])->all();

        return Excel::download(
            new ArrayExport([__('No.'), __('Title'), __('Org unit'), __('From'), __('To'), __('Employees'), __('Status')], $rows),
            'roster-table-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** Typing a search or picking a filter or page size starts again at page 1. */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /** @return Builder<Roster> */
    private function query(): Builder
    {
        // 'approvals' eager loaded: the Status column calls approvalStatus() on every row.
        return Roster::query()
            ->visibleTo(Auth::user())
            ->with(['orgUnit', 'approvals'])
            ->withCount(['items as employees_count' => fn (Builder $q) => $q->select(DB::raw('count(distinct employee_id)'))]);
    }

    /** @return Builder<Roster> */
    private function filteredQuery(): Builder
    {
        $query = $this->query();

        if (trim($this->search) !== '') {
            // addcslashes: a search for "50%" means the text "50%", not "50 followed by anything".
            $like = '%'.addcslashes(trim($this->search), '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('title', 'like', $like));
        }

        if (in_array($this->sortBy, self::SORTABLE, true)) {
            $query->orderBy($this->sortBy, $this->sortDir === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('from_date', 'desc');
        }

        return $query;
    }

    public function render(): View
    {
        // A page size typed into the URL that is not offered falls back to 10.
        $perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 10;

        return view('payroll::livewire.attendance.roster-table', [
            'rows' => $this->filteredQuery()->paginate($perPage),
        ]);
    }
}
