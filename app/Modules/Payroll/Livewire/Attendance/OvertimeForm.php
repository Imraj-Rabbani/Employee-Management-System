<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Actions\RequestOvertime;
use App\Modules\Payroll\Models\AttendanceDay;
use App\Modules\Payroll\Models\OvertimeRequest;
use App\Modules\Workflow\Exceptions\WorkflowException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * "Ask for overtime" (modal "overtime-form"), new or changed after it was sent back. [TA-07]
 *
 *   HR / manager (Overtime tab,    $self = false)  any employee they can see; permission payroll.overtime.manage
 *   ESS          (My Attendance,   $self = true)   always their OWN employee record
 *
 * $self is #[Locked]. Events: "create-overtime" (optional employeeId / date), "edit-overtime" (id). Sends "overtime-saved".
 */
class OvertimeForm extends Component
{
    #[Locked]
    public bool $self = false;

    #[Locked]
    public ?int $requestId = null;

    public string $employeeId = '';

    public string $date = '';

    public string $hours = '';

    public string $detail = '';

    public function mount(bool $self = false): void
    {
        $this->self = $self;
    }

    #[On('create-overtime')]
    public function create(?int $employeeId = null, ?string $date = null): void
    {
        $this->authorizeForm();
        $this->reset('requestId', 'hours', 'detail');
        $this->employeeId = $this->self ? (string) $this->mine()?->id : (string) ($employeeId ?? '');
        $this->date = $date ?? now()->subDay()->toDateString();
        $this->prefill();
        $this->resetValidation();

        Flux::modal('overtime-form')->show();
    }

    #[On('edit-overtime')]
    public function edit(int $id): void
    {
        $this->authorizeForm();
        $request = $this->find($id);
        if (! $request->isEditable()) {
            $this->dispatch('toast', variant: 'error', message: __('Only a request that was sent back can be changed.'));

            return;
        }

        $this->requestId = $request->id;
        $this->employeeId = (string) $request->employee_id;
        $this->date = $request->date->toDateString();
        $this->hours = OvertimeRequest::hoursLabel($request->hours);
        $this->detail = (string) $request->detail;
        $this->resetValidation();

        Flux::modal('overtime-form')->show();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['employeeId', 'date'], true) && $this->requestId === null) {
            $this->prefill();
        }
    }

    public function save(RequestOvertime $action): void
    {
        $this->authorizeForm();
        $this->validate([
            'employeeId' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'hours' => ['required', 'numeric'],
            'detail' => ['required', 'string', 'max:500'],
        ], attributes: ['employeeId' => __('employee'), 'detail' => __('work done')]);

        $employee = $this->self ? $this->mine() : Employee::query()->find((int) $this->employeeId);
        if ($employee === null || ($this->self && $employee->id !== (int) $this->employeeId)) {
            $this->addError('employeeId', __('Choose an employee from the list.'));

            return;
        }

        $existing = $this->requestId ? $this->find($this->requestId) : null;
        $values = ['date' => $this->date, 'hours' => $this->hours, 'detail' => $this->detail];
        if ($problem = $action->problem($employee, $values, $existing)) {
            $this->addError('hours', $problem);

            return;
        }

        try {
            $request = $action->handle($employee, $values, $existing);
        } catch (WorkflowException $e) {
            $this->addError('detail', $e->getMessage());

            return;
        }

        Flux::modal('overtime-form')->close();
        $this->dispatch('overtime-saved');
        $this->dispatch('toast', variant: 'success', message: __(':no sent for approval.', ['no' => $request->number()]));
    }

    /** The overtime the day's punches show, as a starting point. */
    private function prefill(): void
    {
        $minutes = $this->employeeId === '' || $this->date === '' ? 0
            : (int) AttendanceDay::query()->where('employee_id', (int) $this->employeeId)->whereDate('date', $this->date)->value('ot_minutes');
        // Rounded DOWN to quarter hours: 2 h 50 min of overtime → 2.75.
        $this->hours = $minutes > 0 ? OvertimeRequest::hoursLabel(floor($minutes / 15) / 4) : '';
    }

    private function authorizeForm(): void
    {
        if (! $this->self) {
            Gate::authorize('payroll.overtime.manage');
        }
    }

    private function find(int $id): OvertimeRequest
    {
        $employeeIds = $this->self ? [$this->mine()?->id] : Employee::query()->select('id');

        return OvertimeRequest::query()->whereIn('employee_id', $employeeIds)->findOrFail($id);
    }

    private function mine(): ?Employee
    {
        return Employee::query()->where('user_id', Auth::id())->first();
    }

    public function render(): View
    {
        $day = $this->employeeId === '' || $this->date === '' ? null
            : AttendanceDay::query()->with('shift:id,code')->where('employee_id', (int) $this->employeeId)->whereDate('date', $this->date)->first();

        return view('payroll::livewire.attendance.overtime-form', [
            'employees' => $this->self ? [] : Employee::query()->orderBy('code')->get(['id', 'code', 'first_name', 'last_name'])
                ->mapWithKeys(fn (Employee $e) => [$e->id => $e->code.' · '.$e->name])->all(),
            'day' => $day,
        ]);
    }
}
