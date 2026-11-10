<?php

namespace App\Modules\Hrm\Livewire\Employees;

use App\Core\Permissions\PermissionRegistry;
use App\Models\User;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Actions\UserInvitations;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * HRM → Employees → "Invite user": invite someone by email; they register themselves (/join/...)
 * and become an employee. Also lists the invitations not accepted yet (Resend / Cancel).
 *
 * Needs hrm.employee.create (they become an employee) and hrm.employee.login (they get a login).
 * Before sending, the inviter picks the role and sees what it allows. Choosing a role needs
 * core.users.manage; the Administrator role only an Administrator may give (same as LoginBox).
 */
class InviteUser extends Component
{
    public string $email = '';

    public string $roleId = '';

    public static function allowed(): bool
    {
        return Gate::allows('hrm.employee.create') && Gate::allows('hrm.employee.login');
    }

    public function boot(): void
    {
        abort_unless(self::allowed(), 403);
    }

    #[On('invite-user')]
    public function open(): void
    {
        $this->reset('email', 'roleId');
        $this->resetValidation();

        Flux::modal('invite-user-form')->show();
    }

    public function invite(UserInvitations $invitations): void
    {
        $this->validate([
            'email' => ['required', 'email', 'max:255'],
            'roleId' => ['nullable', Rule::in(array_map('strval', array_keys($this->roleOptions())))],
        ]);

        if ($problem = $invitations->inviteProblem($this->email)) {
            $this->addError('email', $problem);

            return;
        }

        $invitations->invite($this->email, $this->roleId !== '' ? Role::find((int) $this->roleId) : null, $this->me());

        Flux::modal('invite-user-form')->close();
        $this->dispatch('toast', message: __('Invitation sent to :email.', ['email' => $this->email]), variant: 'success');
        $this->reset('email', 'roleId');
    }

    public function resend(int $userId, UserInvitations $invitations): void
    {
        $user = $invitations->findPending($userId);

        if ($user === null) {
            return;   // already registered, cancelled, or not of this company
        }

        $invitations->resend($user, $this->me());
        $this->dispatch('toast', message: __('Invitation sent again. The earlier link no longer works.'), variant: 'success');
    }

    public function cancel(int $userId, UserInvitations $invitations): void
    {
        $user = $invitations->findPending($userId);

        if ($user === null) {
            return;
        }

        $invitations->cancel($user);
        $this->dispatch('toast', message: __('Invitation to :email cancelled.', ['email' => $user->email]), variant: 'success');
    }

    /**
     * Roles this user may give: none without core.users.manage; Administrator only for Administrators.
     *
     * @return array<int, string>
     */
    private function roleOptions(): array
    {
        if (! Gate::allows('core.users.manage')) {
            return [];
        }

        return Role::query()
            ->when(! $this->me()->role()?->is_admin, fn ($q) => $q->where('is_admin', false))
            ->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * What the chosen role allows, for the preview: group => labels. null = no role chosen.
     *
     * @return array<string, list<string>>|null
     */
    private function rolePreview(PermissionRegistry $registry): ?array
    {
        $role = $this->roleId !== '' && isset($this->roleOptions()[(int) $this->roleId]) ? Role::find((int) $this->roleId) : null;

        if ($role === null) {
            return null;
        }

        if ($role->is_admin) {
            return [__('Administrator') => [__('Every screen and action of the company')]];
        }

        $keys = array_flip($role->permissionKeys());
        $preview = [];

        foreach ($registry->grouped() as $module => $groups) {
            foreach ($groups as $group => $labels) {
                foreach ($labels as $key => $label) {
                    if (isset($keys[$key])) {
                        $preview[strtoupper($module).' · '.$group][] = __($label);
                    }
                }
            }
        }

        return $preview;
    }

    private function me(): User
    {
        /** @var User $me */
        $me = Auth::user();

        return $me;
    }

    public function render(UserInvitations $invitations, PermissionRegistry $registry): View
    {
        return view('hrm::livewire.employees.invite-user', [
            'roles' => $this->roleOptions(),
            'preview' => $this->rolePreview($registry),
            'pending' => $invitations->pending(),
            'canManageRoles' => Gate::allows('core.roles.manage'),
        ]);
    }
}
