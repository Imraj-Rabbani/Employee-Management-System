<?php

namespace App\Modules\Hrm\Livewire\Leave;

use App\Modules\Hrm\Actions\RequestEncashment;
use App\Modules\Hrm\Data\LeaveYear;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveApplication;
use App\Modules\Hrm\Models\LeaveEncashment;
use App\Modules\Hrm\Models\LeaveType;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Hrm\Services\LeaveLedger;
use App\Modules\Workflow\Exceptions\WorkflowException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * "Encash leave" (modal "encashment-form"), new or changed after a send-back, and "Withdraw". [LV-06]
 *
 *   HR   (HRM → Leave → Encashments, $self = false)  employees they can see; hrm.leave.manage; may withdraw until paid
 *   ESS  (My Leave Requests,          $self = true)   own record only; may withdraw until approved
 *
 * Events: "create-encashment" (optional employeeId), "edit-encashment" (id), "withdraw-encashment" (id).
 * Sends "encashment-saved".
 */
class EncashmentForm extends Component
{
    private const FIELDS = ['leave_type_id' => 'leaveTypeId', 'days' => 'days'];

    #[Locked]
    public bool $self = false;

    #[Locked]
    public ?int $encashmentId = null;

    public string $employeeId = '';

    public string $leaveTypeId = '';

    public string $days = '';

    public string $reason = '';

    public function mount(bool $self = false): void
    {
        $this->self = $self;
    }

    #[On('create-encashment')]
    public function create(?int $employeeId = null): void
    {
        $this->authorizeForm();
        $this->reset('encashmentId', 'leaveTypeId', 'days', 'reason');
        $this->employeeId = $this->self ? (string) $this->mine()?->id : (string) ($employeeId ?? '');
        $this->resetValidation();

        Flux::modal('encashment-form')->show();
    }

    #[On('edit-encashment')]
    public function edit(int $id): void
    {
        $this->authorizeForm();
        $encashment = $this->find($id);
        if (! $encashment->isEditable()) {
            $this->dispatch('toast', variant: 'error', message: __('Only an encashment that was sent back can be changed.'));

            return;
        }

        $this->encashmentId = $encashment->id;
        $this->employeeId = (string) $encashment->employee_id;
        $this->leaveTypeId = (string) $encashment->leave_type_id;
        $this->days = LeaveApplication::daysLabel($encashment->days);   // 2.50 → "2.5"
        $this->reason = (string) $encashment->reason;
        $this->resetValidation();

        Flux::modal('encashment-form')->show();
    }

    public function save(RequestEncashment $action): void
    {
        $this->authorizeForm();
        $this->validate([
            'employeeId' => ['required', 'integer'],
            'leaveTypeId' => ['required', 'integer'],
            'days' => ['required', 'numeric'],
            'reason' => ['nullable', 'string', 'max:500'],
        ], attributes: ['employeeId' => __('employee'), 'leaveTypeId' => __('leave type')]);

        $employee = $this->employee();
        $type = $employee && array_key_exists((int) $this->leaveTypeId, $this->typeOptions($employee)) ? LeaveType::find((int) $this->leaveTypeId) : null;
        if ($employee === null || $type === null) {
            $this->addError($employee === null ? 'employeeId' : 'leaveTypeId', __('Choose from the list.'));

            return;
        }

        $existing = $this->encashmentId ? $this->find($this->encashmentId) : null;
        $values = ['days' => $this->days, 'reason' => $this->reason];
        if ($problem = $action->problem($employee, $type, $values, $existing)) {
            $this->addError(self::FIELDS[$problem->field] ?? 'days', $problem->message);

            return;
        }

        try {
            $encashment = $action->handle($employee, $type, $values, $existing);
        } catch (WorkflowException $e) {
            $this->addError('reason', $e->getMessage());

            return;
        }

        Flux::modal('encashment-form')->close();
        $this->dispatch('encashment-saved');
        $this->dispatch('toast', variant: 'success', message: __(':no sent for approval.', ['no' => $encashment->number()]));
    }

    #[On('withdraw-encashment')]
    public function withdraw(int $id, RequestEncashment $action): void
    {
        $this->authorizeForm();
        $encashment = $this->find($id);

        if ($problem = $action->withdrawProblem($encashment, byHr: ! $this->self)) {
            $this->dispatch('toast', variant: 'error', message: $problem);

            return;
        }

        $action->withdraw($encashment);
        $this->dispatch('encashment-saved');
        $this->dispatch('toast', variant: 'success', message: __(':no withdrawn.', ['no' => $encashment->number()]));
    }

    private function employee(): ?Employee
    {
        if ($this->employeeId === '') {
            return null;
        }
        $employee = $this->self ? $this->mine() : Employee::query()->find((int) $this->employeeId);

        return $employee !== null && $employee->id === (int) $this->employeeId ? $employee : null;
    }

    /** @return array<int, string> the encashable types of the employee's profile */
    private function typeOptions(Employee $employee): array
    {
        $ids = $employee->leaveProfile?->items()->pluck('leave_type_id')->all() ?? [];

        return LeaveType::query()->whereIn('id', $ids)->where('is_active', true)->where('is_encashable', true)->ordered()->get()
            ->mapWithKeys(fn (LeaveType $t) => [$t->id => $t->optionLabel()])->all();
    }

    private function authorizeForm(): void
    {
        if (! $this->self) {
            Gate::authorize('hrm.leave.manage');
        }
    }

    private function find(int $id): LeaveEncashment
    {
        $employeeIds = $this->self ? [$this->mine()?->id] : Employee::query()->select('id');

        return LeaveEncashment::query()->whereIn('employee_id', $employeeIds)->findOrFail($id);
    }

    private function mine(): ?Employee
    {
        return Employee::query()->withoutGlobalScope(VisibleToCurrentUser::class)->where('user_id', Auth::id())->first();
    }

    public function render(): View
    {
        $employee = $this->employee();
        $type = $employee && $this->leaveTypeId !== '' ? LeaveType::find((int) $this->leaveTypeId) : null;

        return view('hrm::livewire.leave.encashment-form', [
            'employees' => $this->self ? [] : Employee::query()->active()->orderBy('code')->get(['id', 'code', 'first_name', 'last_name'])
                ->mapWithKeys(fn (Employee $e) => [$e->id => $e->code.' · '.$e->name])->all(),
            'types' => $employee ? $this->typeOptions($employee) : [],
            'year' => LeaveYear::current(),
            'balance' => $employee && $type ? app(LeaveLedger::class)->balance($employee, $type, LeaveYear::current()) : null,
            'limit' => $type?->encash_limit,
        ]);
    }
}
