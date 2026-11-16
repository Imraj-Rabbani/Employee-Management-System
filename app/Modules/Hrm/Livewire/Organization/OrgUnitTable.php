<?php

namespace App\Modules\Hrm\Livewire\Organization;

use App\Core\Exports\ArrayExport;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Models\OrgUnitType;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** List of org units with their place in the tree. (The tree view is the Org chart page.) */
class OrgUnitTable extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: '')]
    public string $sortBy = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    /** Filter "Level": '' = all */
    #[Url(as: 'org_unit_type_id', except: '')]
    public string $orgUnitTypeId = '';

    #[Url(as: 'per_page', except: 10)]
    public int $perPage = 10;

    /** Columns the user may sort by. Anything else sent from the browser is ignored. */
    private const SORTABLE = ['name', 'code', 'children_count', 'is_active'];

    /** @var array<string, string> refresh the list when its form saved something */
    protected $listeners = ['org-unit-saved' => '$refresh'];

    /** @var array<int, string>|null id => path, built once per render */
    private ?array $paths = null;

    /** @return array<int, string> */
    public function paths(): array
    {
        return $this->paths ??= OrgUnit::pathOptions();
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
        $this->reset('search', 'orgUnitTypeId');
        $this->resetPage();
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (OrgUnit $orgUnit) => [
            $orgUnit->name,
            $orgUnit->parent_id ? ($this->paths()[$orgUnit->parent_id] ?? '') : '-',
            $orgUnit->type->name,
            $orgUnit->code,
            $orgUnit->costCenter->code ?? '-',
            $orgUnit->children_count,
            $orgUnit->is_active ? __('Active') : __('Inactive'),
        ])->all();

        return Excel::download(
            new ArrayExport([__('Name'), __('Under'), __('Level'), __('Code'), __('Cost center'), __('Sub-units'), __('Active')], $rows),
            'org-unit-table-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** Typing a search or picking a filter or page size starts again at page 1. */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'orgUnitTypeId', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /** @return Builder<OrgUnit> */
    private function query(): Builder
    {
        return OrgUnit::query()->with(['type', 'costCenter'])->withCount('children');
    }

    /** @return array<int|string, string> choices of the "Level" filter */
    private function orgUnitTypeIdOptions(): array
    {
        return OrgUnitType::orderBy('sequence')->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])->all();
    }

    /** @return Builder<OrgUnit> */
    private function filteredQuery(): Builder
    {
        $query = $this->query();

        if (trim($this->search) !== '') {
            // addcslashes: a search for "50%" means the text "50%", not "50 followed by anything".
            $like = '%'.addcslashes(trim($this->search), '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('code', 'like', $like));
        }

        if ($this->orgUnitTypeId !== '' && array_key_exists($this->orgUnitTypeId, $this->orgUnitTypeIdOptions())) {
            $query->where('org_unit_type_id', $this->orgUnitTypeId);
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

        return view('hrm::livewire.organization.org-unit-table', [
            'rows' => $this->filteredQuery()->paginate($perPage),
            'orgUnitTypeIdOptions' => $this->orgUnitTypeIdOptions(),
        ]);
    }
}
