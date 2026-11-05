<?php

namespace App\Modules\Core\Livewire;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Modules\Core\Models\Role;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

/**
 * Setup → Roles & Permissions. [FND-05]
 * The page route needs "core.roles.manage"; Livewire re-checks it on every click.
 */
class RolesTable extends DataTable
{
    /** @return Builder<Role> */
    protected function query(): Builder
    {
        return Role::query()->withCount(['permissions', 'userRoles']);
    }

    protected function defaultSort(): array
    {
        return ['is_admin' => 'desc', 'name' => 'asc'];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Role'), 'name')->sortable()->searchable()->alwaysVisible()
                ->format(fn (Role $role) => new HtmlString(Blade::render(
                    '<button type="button" x-on:click="$dispatch(\'edit-role\', { id: {{ $id }} })" class="font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $name }}</button>',
                    ['id' => $role->id, 'name' => $role->name],
                ))),
            Column::make(__('Description'), 'description')->searchable(),
            Column::make(__('Permissions'), 'permissions_count')
                ->format(fn (Role $role) => $role->is_admin
                    ? new HtmlString(Blade::render('<x-ui.badge color="success">{{ __(\'Full access\') }}</x-ui.badge>'))
                    : (string) $role->getAttribute('permissions_count')),
            Column::make(__('Users'), 'user_roles_count')->sortable(),
        ];
    }

    #[On('role-saved')]
    public function refreshList(): void
    {
        // Re-renders the list.
    }
}
