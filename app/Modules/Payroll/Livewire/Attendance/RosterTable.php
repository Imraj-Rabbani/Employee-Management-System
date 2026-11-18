<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Core\Exports\ArrayExport;
use App\Modules\Payroll\Models\Roster;
use App\Modules\Workflow\Exceptions\WorkflowException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Payroll → Attendance → Rosters: the list, newest first.
 * Only rosters of the entities the user can see (Roster::visibleTo). Click the number to open the roster.
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
    protected $listeners = ['roster-saved' => 'clearSelection'];

    /** @var list<string> ids of the ticked rows (the checkboxes). Edit needs one; Delete / Request approval any number. */
    public array $selected = [];

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    /** Filter form → Apply: the new filter values came with this click; start again at page 1. */
    public function applyFilters(): void
    {
        $this->resetPage();
        $this->selected = [];
        Flux::modal('roster-filters')->close();
    }

    /**
     * Toolbar → Request approval: sends every ticked roster that may go (a draft or one sent back) into its
     * workflow, with the same checks as its own page. One toast says what was sent and what was not, and why.
     */
    public function requestApproval(): void
    {
        Gate::authorize('payroll.roster.manage');
        $sent = 0;
        $problems = [];
        foreach (Roster::query()->visibleTo(Auth::user())->with('approvals')->whereKey($this->selected)->get() as $document) {
            $problem = $document->approvalSendProblem();
            if ($problem === null) {
                try {
                    $document->submitForApproval(Auth::user());
                    $sent++;

                    continue;
                } catch (WorkflowException $e) {
                    $problem = $e->getMessage();   // no workflow, nobody to approve ...: written for users
                }
            }
            $problems[] = $document->number().': '.$problem;
        }

        $this->selected = [];
        $this->dispatch('toast',
            message: implode(' ', array_filter([$sent > 0 ? trans_choice(':count sent for approval.|:count sent for approval.', $sent) : null, ...$problems])),
            variant: $problems === [] ? 'success' : ($sent > 0 ? 'warning' : 'error'),
        );
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
        $this->reset('search');
        $this->resetPage();
        $this->selected = [];
        Flux::modal('roster-filters')->close();
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (Roster $roster) => [
            $roster->number(),
            $roster->title,
            $roster->entity->name ?? '-',
            format_date($roster->from_date),
            format_date($roster->to_date),
            $roster->getAttribute('employees_count'),
            $roster->statusBadge()[0],
        ])->all();

        return Excel::download(
            new ArrayExport([__('No.'), __('Title'), __('Entity'), __('From'), __('To'), __('Employees'), __('Status')], $rows),
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
            ->with(['entity', 'approvals'])
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
