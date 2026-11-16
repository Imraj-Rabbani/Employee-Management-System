<?php

namespace App\Modules\Hrm\Livewire\Organization;

use App\Core\Exports\ArrayExport;
use App\Modules\Hrm\Models\Grade;
use App\Modules\Hrm\Models\Position;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** List of positions. */
class PositionTable extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: '')]
    public string $sortBy = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    /** Filter "Grade": '' = all */
    #[Url(as: 'grade_id', except: '')]
    public string $gradeId = '';

    /** Filter "Vacancy": '' = all */
    #[Url(as: 'vacancy', except: '')]
    public string $vacancy = '';

    #[Url(as: 'per_page', except: 10)]
    public int $perPage = 10;

    /** Columns the user may sort by. Anything else sent from the browser is ignored. */
    private const SORTABLE = ['name', 'code', 'is_vacant', 'is_active'];

    /** @var array<string, string> refresh the list when its form saved something */
    protected $listeners = ['position-saved' => '$refresh'];

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
        $this->reset('search', 'gradeId', 'vacancy');
        $this->resetPage();
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (Position $position) => [
            $position->name,
            $position->code,
            $position->orgUnit->name,
            $position->grade->name ?? '-',
            $position->reportsTo->name ?? '-',
            $position->is_vacant ? __('Vacant') : __('Filled'),
            $position->is_active ? __('Active') : __('Inactive'),
        ])->all();

        return Excel::download(
            new ArrayExport([__('Position'), __('Code'), __('Org unit'), __('Grade'), __('Reports to'), __('Vacancy'), __('Active')], $rows),
            'position-table-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** Typing a search or picking a filter or page size starts again at page 1. */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'gradeId', 'vacancy', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /** @return Builder<Position> */
    private function query(): Builder
    {
        return Position::query()->with(['orgUnit', 'grade', 'reportsTo']);
    }

    /** @return array<int|string, string> choices of the "Grade" filter */
    private function gradeIdOptions(): array
    {
        return Grade::ordered()->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])->all();
    }

    /** @return array<int|string, string> choices of the "Vacancy" filter */
    private function vacancyOptions(): array
    {
        return ['vacant' => __('Vacant'), 'filled' => __('Filled')];
    }

    /** @return Builder<Position> */
    private function filteredQuery(): Builder
    {
        $query = $this->query();

        if (trim($this->search) !== '') {
            // addcslashes: a search for "50%" means the text "50%", not "50 followed by anything".
            $like = '%'.addcslashes(trim($this->search), '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('code', 'like', $like));
        }

        if ($this->gradeId !== '' && array_key_exists($this->gradeId, $this->gradeIdOptions())) {
            $query->where('grade_id', $this->gradeId);
        }

        if ($this->vacancy !== '' && array_key_exists($this->vacancy, $this->vacancyOptions())) {
            $query->where('is_vacant', $this->vacancy === 'vacant');
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

        return view('hrm::livewire.organization.position-table', [
            'rows' => $this->filteredQuery()->paginate($perPage),
            'gradeIdOptions' => $this->gradeIdOptions(),
            'vacancyOptions' => $this->vacancyOptions(),
        ]);
    }
}
