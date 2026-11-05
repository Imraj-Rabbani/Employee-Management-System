<?php

namespace App\Modules\Core\Livewire;

use App\Core\Permissions\PermissionRegistry;
use App\Models\Tenant;
use App\Modules\Core\Models\Role;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * New / Edit role (modal "role-form"): name, description and the permission checkboxes.
 * The Administrator role can be renamed but has no checkboxes (it has everything) and cannot be deleted.
 */
class RoleForm extends Component
{
    #[Locked]
    public ?int $roleId = null;

    #[Locked]
    public bool $isAdmin = false;

    public string $name = '';

    public string $description = '';

    /** @var list<string> */
    public array $permissions = [];

    #[On('create-role')]
    public function create(): void
    {
        $this->reset();
        $this->resetValidation();
        Flux::modal('role-form')->show();
    }

    #[On('edit-role')]
    public function edit(int $id): void
    {
        $role = Role::findOrFail($id);

        $this->resetValidation();
        $this->roleId = $role->id;
        $this->isAdmin = $role->is_admin;
        $this->name = $role->name;
        $this->description = (string) $role->description;
        $this->permissions = $role->permissionKeys();

        Flux::modal('role-form')->show();
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100', Rule::unique(Role::class, 'name')->ignore($this->roleId)],
            'description' => ['nullable', 'string', 'max:255'],
            'permissions' => ['array'],
            'permissions.*' => [Rule::in(app(PermissionRegistry::class)->keys())],
        ];
    }

    public function save(): void
    {
        $this->validate();

        $role = Role::updateOrCreate(['id' => $this->roleId], [
            'name' => $this->name,
            'description' => $this->description !== '' ? $this->description : null,
        ]);

        if (! $role->is_admin) {
            $role->syncPermissions($this->permissions);
        }

        Flux::modal('role-form')->close();
        $this->dispatch('role-saved');
        $this->dispatch('toast', message: __(':name saved.', ['name' => $role->name]), variant: 'success');
    }

    public function delete(): void
    {
        $role = Role::findOrFail($this->roleId);

        if ($role->is_admin) {
            $this->addError('name', __('The Administrator role cannot be deleted.'));

            return;
        }

        if ($role->userRoles()->exists()) {
            $this->addError('name', __('This role is still given to users. Give them another role first.'));

            return;
        }

        $role->delete();

        Flux::modal('role-form')->close();
        $this->dispatch('role-saved');
        $this->dispatch('toast', message: __(':name deleted.', ['name' => $role->name]), variant: 'success');
    }

    public function render(): View
    {
        return view('core::livewire.role-form', [
            'modules' => $this->moduleGroups(),
        ]);
    }

    /**
     * Permission groups of the modules this company has, with a heading per module.
     *
     * @return array<string, array<string, array<string, string>>> module label => group => key => label
     */
    private function moduleGroups(): array
    {
        $tenant = tenant();
        $result = [];

        foreach (app(PermissionRegistry::class)->grouped() as $module => $groups) {
            if ($tenant?->hasModule($module)) {
                $result[Tenant::OPTIONAL_MODULES[$module] ?? __('Setup')] = $groups;
            }
        }

        return $result;
    }
}
