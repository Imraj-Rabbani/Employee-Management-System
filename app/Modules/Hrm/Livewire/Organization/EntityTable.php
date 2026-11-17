<?php

namespace App\Modules\Hrm\Livewire\Organization;

use App\Core\Exports\ArrayExport;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Hrm\Models\EntityType;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** List of entities (all types, or one: ?type=3). Click a row's name to edit it. */
class EntityTable extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: '')]
    public string $sortBy = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    /** Filter "Type": '' = all. The Entity types tab links here with ?type=… */
    #[Url(as: 'type', except: '')]
    public string $entityTypeId = '';

    #[Url(as: 'per_page', except: 10)]
    public int $perPage = 10;

    /** Columns the user may sort by. Anything else sent from the browser is ignored. */
    private const SORTABLE = ['name', 'code', 'current_assignments_count', 'is_active'];

    /** @var array<string, string> refresh the list when its form saved something */
    protected $listeners = ['entity-saved' => '$refresh'];

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
        $this->reset('search', 'entityTypeId');
        $this->resetPage();
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (Entity $entity) => [
            $entity->name,
            $entity->type->name,
            $entity->code,
            $entity->head->name ?? '-',
            $entity->parent ? $entity->parent->label() : '-',
            $entity->current_assignments_count,
            $entity->is_active ? __('Active') : __('Inactive'),
        ])->all();

        return Excel::download(
            new ArrayExport([__('Name'), __('Type'), __('Code'), __('Head'), __('Parent'), __('Employees'), __('Active')], $rows),
            'entity-table-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** Typing a search or picking a filter or page size starts again at page 1. */
    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'entityTypeId', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /** @return Builder<Entity> */
    private function query(): Builder
    {
        // current_assignments_count: people currently in the entity (open employee_entities rows).
        return Entity::query()->with(['type', 'head', 'parent.type'])->withCount('currentAssignments');
    }

    /** @return array<int|string, string> choices of the "Type" filter */
    private function entityTypeIdOptions(): array
    {
        return EntityType::ordered()->pluck('name', 'id')
            ->mapWithKeys(fn ($name, $id) => [(string) $id => $name])->all();
    }

    /** @return Builder<Entity> */
    private function filteredQuery(): Builder
    {
        $query = $this->query();

        if (trim($this->search) !== '') {
            // addcslashes: a search for "50%" means the text "50%", not "50 followed by anything".
            $like = '%'.addcslashes(trim($this->search), '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('code', 'like', $like));
        }

        if ($this->entityTypeId !== '' && array_key_exists($this->entityTypeId, $this->entityTypeIdOptions())) {
            $query->where('entity_type_id', $this->entityTypeId);
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

        return view('hrm::livewire.organization.entity-table', [
            'rows' => $this->filteredQuery()->paginate($perPage),
            'entityTypeIdOptions' => $this->entityTypeIdOptions(),
        ]);
    }
}
