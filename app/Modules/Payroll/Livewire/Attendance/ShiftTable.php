<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Core\Exports\ArrayExport;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Payroll\Models\Shift;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** List of shifts. Click a row's name to edit it. */
class ShiftTable extends Component
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
    private const SORTABLE = ['name', 'code', 'start_time', 'hours', 'grace_minutes', 'employees_count', 'is_default', 'is_active'];

    /** @var array<string, string> refresh the list when its form saved something */
    protected $listeners = ['shift-saved' => 'clearSelection'];

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
        Flux::modal('shift-filters')->close();
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
        Flux::modal('shift-filters')->close();
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (Shift $shift) => [
            $shift->name,
            $shift->code,
            $shift->timesLabel(),
            $shift->hours,
            $shift->grace_minutes ? __(':n min', ['n' => $shift->grace_minutes]) : '—',
            $shift->entity->name ?? __('Whole company'),
            $shift->employees_count,
            $shift->getAttribute('is_default') ? __('Default') : '',
            $shift->is_active ? __('Active') : __('Inactive'),
        ])->all();

        return Excel::download(
            new ArrayExport([__('Name'), __('Code'), __('Time'), __('Hours'), __('Grace'), __('For'), __('Employees'), __('Default'), __('Active')], $rows),
            'shift-table-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** Typing a search or picking a filter or page size starts again at page 1. */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /** @return Builder<Shift> */
    private function query(): Builder
    {
        // Employees counted company-wide (not only those this user may see): it says whether the shift is in use.
        return Shift::query()->with('entity')
            ->withCount(['employees' => fn (Builder $q) => $q->withoutGlobalScope(VisibleToCurrentUser::class)]);
    }

    /** @return Builder<Shift> */
    private function filteredQuery(): Builder
    {
        $query = $this->query();

        if (trim($this->search) !== '') {
            // addcslashes: a search for "50%" means the text "50%", not "50 followed by anything".
            $like = '%'.addcslashes(trim($this->search), '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('code', 'like', $like));
        }

        if (in_array($this->sortBy, self::SORTABLE, true)) {
            $query->orderBy($this->sortBy, $this->sortDir === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('name', 'asc');
        }

        return $query;
    }

    public function render(): View
    {
        // A page size typed into the URL that is not offered falls back to 10.
        $perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 10;

        return view('payroll::livewire.attendance.shift-table', [
            'rows' => $this->filteredQuery()->paginate($perPage),
        ]);
    }
}
