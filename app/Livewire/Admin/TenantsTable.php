<?php

namespace App\Livewire\Admin;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Core\Livewire\Table\SelectFilter;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

/**
 * Platform admin → Companies. Lists every tenant from the central database.
 * Only reachable through the /admin routes (can:super-admin).
 */
class TenantsTable extends DataTable
{
    /**
     * Runs on the first load AND on every later Livewire request (/livewire/update),
     * so nobody but a super admin can use this component, even by crafting requests.
     */
    public function boot(): void
    {
        Gate::authorize('super-admin');
    }

    /** @return Builder<Tenant> */
    protected function query(): Builder
    {
        return Tenant::query()->withCount('users');
    }

    protected function columns(): array
    {
        return [
            // The code opens the Edit form: it sends an "edit-tenant" browser event that EditTenantForm listens for.
            Column::make(__('Code'), 'code')->sortable()->searchable()->alwaysVisible()
                ->format(fn (Tenant $tenant) => new HtmlString(Blade::render(
                    '<button type="button" x-on:click="$dispatch(\'edit-tenant\', { id: {{ $id }} })" class="font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200" title="{{ __(\'Edit\') }}">{{ $code }}</button>',
                    ['id' => $tenant->id, 'code' => $tenant->code],
                ))),
            Column::make(__('Company'), 'name')->sortable()->searchable(),
            Column::make(__('Database'), 'database')->searchable(),
            Column::make(__('Modules'), 'modules')
                ->format(fn (Tenant $tenant) => collect($tenant->modules ?? [])
                    ->map(fn (string $key) => Tenant::OPTIONAL_MODULES[$key] ?? $key)
                    ->implode(', ') ?: '—'),
            Column::make(__('Users'), 'users_count')->sortable(),
            Column::make(__('Status'), 'is_active')
                ->format(fn (Tenant $tenant) => new HtmlString(Blade::render(
                    '<x-ui.badge :color="$color" dot>{{ $text }}</x-ui.badge>',
                    $tenant->is_active
                        ? ['color' => 'success', 'text' => __('Active')]
                        : ['color' => 'danger', 'text' => __('Inactive')],
                ))),
            Column::make(__('Created'), 'created_at')
                ->sortable()
                ->format(fn (Tenant $tenant) => format_date($tenant->created_at)),
        ];
    }

    protected function filters(): array
    {
        return [
            SelectFilter::make('status', __('Status'), ['active' => __('Active'), 'inactive' => __('Inactive')])
                ->query(fn (Builder $query, string $value) => $query->where('is_active', $value === 'active')),
        ];
    }

    /** The New / Edit company forms announce a change; re-render to show it. */
    #[On('tenant-saved')]
    public function refreshList(): void
    {
        $this->resetPage();
    }
}
