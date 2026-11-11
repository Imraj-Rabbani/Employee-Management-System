<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Actions\RequestCorrection;
use App\Modules\Payroll\Models\AttendanceCorrection;
use App\Modules\Payroll\Models\AttendanceDay;
use App\Modules\Workflow\Exceptions\WorkflowException;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * "Request a correction" (modal "correction-form"), new or changed after it was sent back. [TA-06]
 *
 *   HR      (Corrections tab, $self = false)  any employee they can see; permission payroll.correction.manage
 *   ESS     (My Attendance,   $self = true)   always their OWN employee record, with the company's limits
 *
 * $self is #[Locked]: the browser cannot switch an employee's form into the HR form.
 * Events: "create-correction" (optional employeeId / date), "edit-correction" (id). Sends "corrections-saved".
 */
class CorrectionForm extends Component
{
    #[Locked]
    public bool $self = false;

    #[Locked]
    public ?int $correctionId = null;

    public string $employeeId = '';

    public string $date = '';

    public string $in = '';

    public string $out = '';

    public string $reason = '';

    public function mount(bool $self = false): void
    {
        $this->self = $self;
    }

    #[On('create-correction')]
    public function create(?int $employeeId = null, ?string $date = null): void
    {
        $this->authorizeForm();
        $this->reset('correctionId', 'in', 'out', 'reason');
        $this->employeeId = $this->self ? (string) ($this->mine()?->id ?? '') : (string) ($employeeId ?? '');
        $this->date = $date ?? now()->subDay()->toDateString();
        $this->prefill();
        $this->resetValidation();

        Flux::modal('correction-form')->show();
    }

    #[On('edit-correction')]
    public function edit(int $id): void
    {
        $this->authorizeForm();
        $correction = $this->find($id);
        if (! $correction->isEditable()) {
            $this->dispatch('toast', variant: 'error', message: __('Only a correction that was sent back can be changed.'));

            return;
        }

        $this->correctionId = $correction->id;
        $this->employeeId = (string) $correction->employee_id;
        $this->date = $correction->date->toDateString();
        $this->in = $correction->requested_in_at?->format('H:i') ?? '';
        $this->out = $correction->requested_out_at?->format('H:i') ?? '';
        $this->reason = $correction->reason;
        $this->resetValidation();

        Flux::modal('correction-form')->show();
    }

    /** Picking another employee or day: start from that day's processed in / out. */
    public function updated(string $property): void
    {
        if (in_array($property, ['employeeId', 'date'], true) && $this->correctionId === null) {
            $this->prefill();
        }
    }

    public function save(RequestCorrection $action): void
    {
        $this->authorizeForm();
        $this->validate([
            'employeeId' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
            'in' => ['nullable', 'date_format:H:i'],
            'out' => ['nullable', 'date_format:H:i'],
            'reason' => ['required', 'string', 'max:500'],
        ], attributes: ['employeeId' => __('employee'), 'in' => __('in'), 'out' => __('out')]);

        // Through the visibility scope (HR) or the login link (ESS): someone else's id gives an error, not their data.
        $employee = $this->self ? $this->mine() : Employee::query()->find((int) $this->employeeId);
        if ($employee === null || ($this->self && $employee->id !== (int) $this->employeeId)) {
            $this->addError('employeeId', __('Choose an employee from the list.'));

            return;
        }

        $existing = $this->correctionId ? $this->find($this->correctionId) : null;
        $values = ['date' => $this->date, 'in' => $this->in, 'out' => $this->out, 'reason' => $this->reason];
        if ($problem = $action->problem($employee, $values, $this->self, $existing)) {
            $this->addError('date', $problem);

            return;
        }

        try {
            $correction = $action->handle($employee, $values, $existing);
        } catch (WorkflowException $e) {
            $this->addError('reason', $e->getMessage());

            return;
        }

        Flux::modal('correction-form')->close();
        $this->dispatch('corrections-saved');
        $this->dispatch('toast', variant: 'success', message: __(':no sent for approval.', ['no' => $correction->number()]));
    }

    /** The day's processed in / out, as a starting point (HR mostly fixes one end). */
    private function prefill(): void
    {
        $day = $this->employeeId === '' || $this->date === '' ? null
            : AttendanceDay::query()->where('employee_id', (int) $this->employeeId)->whereDate('date', $this->date)->first();
        $this->in = $day?->in_at?->format('H:i') ?? '';
        $this->out = $day?->out_at?->format('H:i') ?? '';
    }

    private function authorizeForm(): void
    {
        if (! $this->self) {
            Gate::authorize('payroll.correction.manage');
        }
    }

    /** A correction this user may change: their own (ESS), or of an employee they can see (HR). Else 404. */
    private function find(int $id): AttendanceCorrection
    {
        $employeeIds = $this->self ? [$this->mine()?->id] : Employee::query()->select('id');

        return AttendanceCorrection::query()->whereIn('employee_id', $employeeIds)->findOrFail($id);
    }

    private function mine(): ?Employee
    {
        return Employee::query()->where('user_id', Auth::id())->first();
    }

    public function render(): View
    {
        $day = $this->employeeId === '' || $this->date === '' ? null
            : AttendanceDay::query()->with('shift:id,code')->where('employee_id', (int) $this->employeeId)->whereDate('date', $this->date)->first();

        return view('payroll::livewire.attendance.correction-form', [
            'employees' => $this->self ? [] : Employee::query()->orderBy('code')->get(['id', 'code', 'first_name', 'last_name'])
                ->mapWithKeys(fn (Employee $e) => [$e->id => $e->code.' · '.$e->name])->all(),
            'day' => $day,
            'today' => now()->toDateString(),
        ]);
    }
}
