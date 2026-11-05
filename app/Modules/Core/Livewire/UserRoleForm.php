<?php

namespace App\Modules\Core\Livewire;

use App\Models\User;
use App\Modules\Core\Models\Role;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Setup → Users → click a user's role (modal "user-role-form"). Needs "core.users.manage".
 *
 * Safety rules:
 *   - you cannot change your own role (so an admin cannot lock themselves out)
 *   - only an Administrator may give the Administrator role, or change an Administrator's role
 *     (otherwise anyone with core.users.manage could make a friend admin)
 */
class UserRoleForm extends Component
{
    #[Locked]
    public ?int $userId = null;

    public string $userName = '';

    public ?int $roleId = null;

    public function boot(): void
    {
        Gate::authorize('core.users.manage');
    }

    #[On('edit-user-role')]
    public function edit(int $id): void
    {
        $user = $this->companyUser($id);

        $this->resetValidation();
        $this->userId = $user->id;
        $this->userName = $user->name;
        $this->roleId = $user->role()?->id;

        Flux::modal('user-role-form')->show();
    }

    public function save(): void
    {
        $this->validate(['roleId' => ['required', Rule::exists(Role::class, 'id')]]);

        $user = $this->companyUser((int) $this->userId);
        $newRole = Role::findOrFail($this->roleId);

        if ($user->is(auth()->user())) {
            $this->addError('roleId', __('You cannot change your own role.'));

            return;
        }

        $touchesAdmin = $newRole->is_admin || $user->role()?->is_admin;
        if ($touchesAdmin && ! $this->currentUserIsAdmin()) {
            $this->addError('roleId', __('Only an Administrator can give or take away the Administrator role.'));

            return;
        }

        $newRole->assignTo($user);

        Flux::modal('user-role-form')->close();
        $this->dispatch('user-role-saved');
        $this->dispatch('toast', message: __(':user is now :role.', ['user' => $user->name, 'role' => $newRole->name]), variant: 'success');
    }

    public function render(): View
    {
        return view('core::livewire.user-role-form', [
            'roles' => Role::orderByDesc('is_admin')->orderBy('name')->pluck('name', 'id')->all(),
        ]);
    }

    /** Only logins of the CURRENT company: never another company's user, whatever id is sent. */
    private function companyUser(int $id): User
    {
        return User::where('tenant_id', tenant()?->id)->findOrFail($id);
    }

    private function currentUserIsAdmin(): bool
    {
        /** @var User $me */
        $me = auth()->user();

        return (bool) $me->role()?->is_admin;
    }
}
