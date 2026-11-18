<?php

namespace App\Livewire\Admin;

use App\Core\Exports\ArrayExport;
use App\Models\Tenant;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Platform admin → Companies. Lists every tenant from the central database.
 * Only reachable through the /admin routes (can:super-admin).
 * View: resources/views/livewire/admin/tenants-table.blade.php
 */
class TenantsTable extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    #[Url(as: 'sort', except: '')]
    public string $sortBy = '';

    #[Url(as: 'dir', except: 'asc')]
    public string $sortDir = 'asc';

    /** '' = all, 'active', 'inactive' */
    #[Url(except: '')]
    public string $status = '';

    #[Url(as: 'per_page', except: 10)]
    public int $perPage = 10;

    private const SORTABLE = ['code', 'name', 'users_count', 'created_at'];

    /**
     * Runs on the first load AND on every later Livewire request (/livewire/update),
     * so nobody but a super admin can use this component, even by crafting requests.
     */
    public function boot(): void
    {
        Gate::authorize('super-admin');
    }

    public function sort(string $field): void
    {
        if (! in_array($field, self::SORTABLE, true)) {
            return;
        }

        $this->sortDir = $this->sortBy === $field && $this->sortDir === 'asc' ? 'desc' : 'asc';
        $this->sortBy = $field;
    }

    /** @var list<string> ids of the ticked rows (the checkboxes). Edit needs exactly one. */
    public array $selected = [];

    /** Filter form → Apply: the new filter values came with this click; start again at page 1. */
    public function applyFilters(): void
    {
        $this->resetPage();
        $this->selected = [];
        Flux::modal('tenants-filters')->close();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'status');
        $this->resetPage();
        $this->selected = [];
        Flux::modal('tenants-filters')->close();
    }

    public function export(): BinaryFileResponse
    {
        $rows = $this->filteredQuery()->get()->map(fn (Tenant $tenant) => [
            $tenant->code,
            $tenant->name,
            $tenant->database,
            $this->moduleNames($tenant),
            $tenant->getAttribute('users_count'),
            $tenant->is_active ? __('Active') : __('Inactive'),
            format_date($tenant->created_at),
        ])->all();

        return Excel::download(
            new ArrayExport([__('Code'), __('Company'), __('Database'), __('Modules'), __('Users'), __('Status'), __('Created')], $rows),
            'tenants-table-'.now()->format('Y-m-d').'.xlsx',
        );
    }

    /** The New / Edit company forms announce a change; re-render to show it. */
    #[On('tenant-saved')]
    public function refreshList(): void
    {
        $this->resetPage();
        $this->selected = [];
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['search', 'status', 'perPage'], true)) {
            $this->resetPage();
        }
    }

    /** "HRM, Payroll" from the stored keys ['hrm', 'payroll']. Used by the view and the export. */
    public function moduleNames(Tenant $tenant): string
    {
        return collect($tenant->modules ?? [])
            ->map(fn (string $key) => Tenant::OPTIONAL_MODULES[$key] ?? $key)
            ->implode(', ') ?: '—';
    }

    /** @return Builder<Tenant> */
    private function filteredQuery(): Builder
    {
        $query = Tenant::query()->withCount('users');

        if (trim($this->search) !== '') {
            $like = '%'.addcslashes(trim($this->search), '%_\\').'%';
            $query->where(fn (Builder $q) => $q->where('code', 'like', $like)
                ->orWhere('name', 'like', $like)
                ->orWhere('database', 'like', $like));
        }

        if ($this->status === 'active') {
            $query->where('is_active', true);
        } elseif ($this->status === 'inactive') {
            $query->where('is_active', false);
        }

        if (in_array($this->sortBy, self::SORTABLE, true)) {
            $query->orderBy($this->sortBy, $this->sortDir === 'desc' ? 'desc' : 'asc');
        }

        return $query;
    }

    public function render(): View
    {
        $perPage = in_array($this->perPage, [10, 25, 50, 100], true) ? $this->perPage : 10;

        return view('livewire.admin.tenants-table', [
            'rows' => $this->filteredQuery()->paginate($perPage),
        ]);
    }
}
