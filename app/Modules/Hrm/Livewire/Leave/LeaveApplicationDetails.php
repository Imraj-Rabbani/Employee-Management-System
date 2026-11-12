<?php

namespace App\Modules\Hrm\Livewire\Leave;

use App\Modules\Hrm\Actions\WithdrawLeave;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveApplication;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * One leave application (modal "leave-details"): details, counted days, approval timeline, and the buttons
 * "Change and send again" (sent back) and "Withdraw" (WithdrawLeave). [LV-04]
 *
 *   HR   ($self = false)  applications of employees they can see; hrm.leave.manage; may withdraw started leave
 *   ESS  ($self = true)   own applications only
 *
 * Event "show-leave" (id). Sends "leave-saved" after a withdrawal.
 */
class LeaveApplicationDetails extends Component
{
    #[Locked]
    public bool $self = false;

    #[Locked]
    public ?int $applicationId = null;

    public string $withdrawReason = '';

    public function mount(bool $self = false): void
    {
        $this->self = $self;
    }

    #[On('show-leave')]
    public function show(int $id): void
    {
        $this->applicationId = $this->find($id)->id;
        $this->withdrawReason = '';
        $this->resetValidation();

        Flux::modal('leave-details')->show();
    }

    public function withdraw(WithdrawLeave $action): void
    {
        $application = $this->find((int) $this->applicationId);
        $this->validate(['withdrawReason' => ['nullable', 'string', 'max:500']]);

        if ($problem = $action->problem($application, byHr: ! $this->self)) {
            $this->addError('withdrawReason', $problem);

            return;
        }

        $action->handle($application, $this->withdrawReason);

        Flux::modal('leave-details')->close();
        $this->dispatch('leave-saved');
        $this->dispatch('toast', variant: 'success', message: __(':no withdrawn.', ['no' => $application->number()]));
    }

    private function find(int $id): LeaveApplication
    {
        if (! $this->self) {
            Gate::authorize('hrm.leave.manage');
        }
        $employeeIds = $this->self
            ? [Employee::query()->withoutGlobalScope(VisibleToCurrentUser::class)->where('user_id', Auth::id())->value('id')]
            : Employee::query()->select('id');

        return LeaveApplication::query()->whereIn('employee_id', $employeeIds)->findOrFail($id);
    }

    public function render(): View
    {
        $application = $this->applicationId
            ? $this->find($this->applicationId)->load(['employee', 'leaveType', 'delegatee', 'leaveDays', 'approvals'])
            : null;

        return view('hrm::livewire.leave.leave-application-details', [
            'application' => $application,
            'withdrawProblem' => $application ? app(WithdrawLeave::class)->problem($application, byHr: ! $this->self) : null,
        ]);
    }
}
