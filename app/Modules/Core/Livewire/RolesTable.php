<?php

namespace App\Modules\Core\Livewire;

use App\Core\Exports\ArrayExport;
use App\Modules\Core\Models\Role;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Setup → Roles & Permissions.
 * The page route needs "core.roles.manage"; Livewire re-checks it on every click.
 * View: app/Modules/Core/resources/views/livewire/roles-table.blade.php
 */
class RolesTable extends Component
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

    private const SORTABLE = ['name', 'user_roles_count'];

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
        Flux::modal('role-filters')->close();
    }

    public function clearFilters(): void
    {
        $this->reset('search');
        $this->resetPage();
        $this->selected = [];
        Flux::modal('role-filters')->close();
    }

    public function sort(string $field): void
    {
        if (! in_array($field, self::SORTABLE, true)) {
            return;
        }

        $this->sortDir = $this->sortBy === $field && $this->sortDir === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $field;
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (Role $role) => [
            $role->name,
            $role->description,
            $role->is_admin ? __('Full access') : $role->getAttribute('permissions_count'),
            $role->getAttribute('user_roles_count'),
        ])->all();

        return Excel::download(
            new ArrayExport([__('Role'), __('Description'), __('Permissions'), __('Users')], $rows),
            'roles-table-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** The form saved a role: re-render the list. */
    #[On('role-saved')]
    public function refreshList(): void
    {
        $this->selected = [];
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /** @return Builder<Role> */
    private function filteredQuery(): Builder
    {
        $query = Role::query()->withCount(['permissions', 'userRoles']);

        if (trim($this->search) !== '') {
            $like = '%'.addcslashes(trim($this->search), '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('name', 'like', $like)->orWhere('description', 'like', $like));
        }

        if (in_array($this->sortBy, self::SORTABLE, true)) {
            $query->orderBy($this->sortBy, $this->sortDir === 'desc' ? 'desc' : 'asc');
        } else {
            $query->orderBy('is_admin', 'desc')->orderBy('name'); // Administrator first, then A-Z
        }

        return $query;
    }

    public function render(): View
    {
        $perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 10;

        return view('core::livewire.roles-table', [
            'rows' => $this->filteredQuery()->paginate($perPage),
        ]);
    }
}
