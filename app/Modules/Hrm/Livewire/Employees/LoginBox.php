<?php

namespace App\Modules\Hrm\Livewire\Employees;

use App\Models\User;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Actions\EmployeeLogin;
use App\Modules\Hrm\Models\Employee;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Profile header → "Login": who logs in as this employee, and Give login / Resend / Unlink. [EMP-07]
 * Changes need EmployeePolicy::manageLogin (hrm.employee.login + org-unit scope).
 * Choosing a role needs core.users.manage; the Administrator role only an Administrator may give
 * (same rules as Setup → Users).
 */
class LoginBox extends Component
{
    #[Locked]
    public int $employeeId;

    /** 'invite' or 'link' (the two tabs of the modal) */
    public string $mode = 'invite';

    public string $email = '';

    public string $roleId = '';

    public string $userId = '';

    public function mount(Employee $employee): void
    {
        $this->employeeId = $employee->id;
    }

    public function open(): void
    {
        $employee = $this->employee(manage: true);

        $this->mode = 'invite';
        $this->email = (string) ($employee->office_email ?? $employee->personal_email ?? '');
        $this->roleId = '';
        $this->userId = '';
        $this->resetValidation();

        Flux::modal('employee-login-form')->show();
    }

    public function invite(EmployeeLogin $logins): void
    {
        $employee = $this->employee(manage: true);
        $this->validate([
            'email' => ['required', 'email', 'max:255'],
            'roleId' => ['nullable', Rule::in(array_map('strval', array_keys($this->roleOptions())))],
        ]);

        if ($problem = $logins->inviteProblem($employee, $this->email)) {
            $this->addError('email', $problem);

            return;
        }

        $logins->invite($employee, $this->email, $this->roleId !== '' ? Role::find((int) $this->roleId) : null, $this->me());
        $this->done(__('Invitation sent to :email.', ['email' => $this->email]));
    }

    public function link(EmployeeLogin $logins): void
    {
        $employee = $this->employee(manage: true);
        $this->validate(['userId' => ['required']], attributes: ['userId' => __('login')]);

        // Only logins of THIS company, whatever id the browser sends.
        $user = User::where('tenant_id', tenant()?->id)->find((int) $this->userId);
        if ($user === null || ($problem = $logins->linkProblem($employee, $user))) {
            $this->addError('userId', $problem ?? __('This login does not belong to this company.'));

            return;
        }

        $logins->link($employee, $user);
        $this->done(__(':name can now log in as :email.', ['name' => $employee->name, 'email' => $user->email]));
    }

    public function resend(EmployeeLogin $logins): void
    {
        $employee = $this->employee(manage: true);

        if ($problem = $logins->resendProblem($employee)) {
            $this->dispatch('toast', message: $problem, variant: 'error');

            return;
        }

        $logins->resend($employee, $this->me());
        $this->dispatch('toast', message: __('Invitation sent again. The earlier link no longer works.'), variant: 'success');
    }

    public function unlink(EmployeeLogin $logins): void
    {
        $employee = $this->employee(manage: true);

        if ($employee->user_id === Auth::id()) {
            $this->dispatch('toast', message: __('You cannot unlink your own login.'), variant: 'error');

            return;
        }

        $logins->unlink($employee);
        $this->dispatch('toast', message: __('Login unlinked. The login itself still exists (Setup → Users).'), variant: 'success');
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
     * Logins of this company that are not linked to any employee yet.
     *
     * @return array<int, string>
     */
    private function freeLogins(): array
    {
        $taken = Employee::withoutGlobalScopes()->whereNotNull('user_id')->pluck('user_id')->all();

        return User::where('tenant_id', tenant()?->id)->where('is_super_admin', false)->whereNotIn('id', $taken)
            ->orderBy('name')->get(['id', 'name', 'email'])
            ->mapWithKeys(fn (User $u) => [$u->id => "{$u->name} ({$u->email})"])->all();
    }

    private function employee(bool $manage = false): Employee
    {
        $employee = Employee::findOrFail($this->employeeId);
        Gate::authorize($manage ? 'manageLogin' : 'view', $employee);

        return $employee;
    }

    private function me(): User
    {
        /** @var User $me */
        $me = Auth::user();

        return $me;
    }

    private function done(string $message): void
    {
        Flux::modal('employee-login-form')->close();
        $this->dispatch('toast', message: $message, variant: 'success');
    }

    public function render(): View
    {
        $employee = $this->employee();
        $user = $employee->user;
        $canManage = Gate::allows('manageLogin', $employee);

        return view('hrm::livewire.employees.login-box', [
            'employee' => $employee,
            'user' => $user,
            'role' => $user?->role(),
            'canManage' => $canManage,
            'roles' => $canManage ? $this->roleOptions() : [],
            'freeLogins' => $canManage && $user === null ? $this->freeLogins() : [],
        ]);
    }
}
