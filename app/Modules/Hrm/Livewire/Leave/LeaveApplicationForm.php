<?php

namespace App\Modules\Hrm\Livewire\Leave;

use App\Modules\Hrm\Actions\ApplyForLeave;
use App\Modules\Hrm\Data\LeaveCheck;
use App\Modules\Hrm\Data\LeaveYear;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveApplication;
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
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * "Apply for leave" (modal "leave-form"), new or changed after it was sent back. [LV-04]
 *
 *   HR       (HRM → Leave → Applications,   $self = false)  any employee they can see; hrm.leave.manage;
 *            HR-only types allowed, no notice / post-facto limits
 *   ESS      (My Leave Requests,             $self = true)   always their OWN employee record
 *
 * While typing, the form shows what ApplyForLeave::evaluate() says: the days it costs and every problem.
 * Events: "create-leave" (optional employeeId), "edit-leave" (id). Sends "leave-saved".
 */
class LeaveApplicationForm extends Component
{
    use WithFileUploads;

    /** Livewire field => the field names LeaveRules / ApplyForLeave use in their problems */
    private const FIELDS = [
        'leave_type_id' => 'leaveTypeId', 'from_date' => 'fromDate', 'to_date' => 'toDate',
        'is_half_day' => 'isHalfDay', 'reason' => 'reason', 'attachment' => 'upload',
    ];

    #[Locked]
    public bool $self = false;

    #[Locked]
    public ?int $applicationId = null;

    public string $employeeId = '';

    public string $leaveTypeId = '';

    public string $fromDate = '';

    public string $toDate = '';

    public bool $isHalfDay = false;

    public string $delegateeId = '';

    public string $contact = '';

    public string $reason = '';

    /** @var TemporaryUploadedFile|null */
    public $upload = null;

    public function mount(bool $self = false): void
    {
        $this->self = $self;
    }

    #[On('create-leave')]
    public function create(?int $employeeId = null): void
    {
        $this->authorizeForm();
        $this->reset('applicationId', 'leaveTypeId', 'isHalfDay', 'delegateeId', 'contact', 'reason', 'upload');
        $this->employeeId = $this->self ? (string) $this->mine()?->id : (string) ($employeeId ?? '');
        $this->fromDate = $this->toDate = now(settings('timezone', config('app.timezone')))->toDateString();
        $this->resetValidation();

        Flux::modal('leave-form')->show();
    }

    #[On('edit-leave')]
    public function edit(int $id): void
    {
        $this->authorizeForm();
        $application = $this->find($id);
        if (! $application->isEditable()) {
            $this->dispatch('toast', variant: 'error', message: __('Only an application that was sent back can be changed.'));

            return;
        }

        $this->applicationId = $application->id;
        $this->employeeId = (string) $application->employee_id;
        $this->leaveTypeId = (string) $application->leave_type_id;
        $this->fromDate = $application->from_date->toDateString();
        $this->toDate = $application->to_date->toDateString();
        $this->isHalfDay = $application->is_half_day;
        $this->delegateeId = (string) $application->delegatee_id;
        $this->contact = (string) $application->contact_during_leave;
        $this->reason = (string) $application->reason;
        $this->upload = null;
        $this->resetValidation();

        Flux::modal('leave-form')->show();
    }

    /** A half day is one date: the last day follows the first. */
    public function updated(string $property): void
    {
        if ($this->isHalfDay && in_array($property, ['isHalfDay', 'fromDate'], true)) {
            $this->toDate = $this->fromDate;
        }
        if ($property === 'fromDate' && $this->toDate !== '' && $this->toDate < $this->fromDate) {
            $this->toDate = $this->fromDate;
        }
    }

    public function save(ApplyForLeave $action): void
    {
        $this->authorizeForm();
        $this->validate([
            'employeeId' => ['required', 'integer'],
            'leaveTypeId' => ['required', 'integer'],
            'fromDate' => ['required', 'date_format:Y-m-d'],
            'toDate' => ['required', 'date_format:Y-m-d'],
            'isHalfDay' => ['boolean'],
            'delegateeId' => ['nullable', 'integer', 'different:employeeId'],
            'contact' => ['nullable', 'string', 'max:100'],
            'reason' => ['nullable', 'string', 'max:1000'],
            'upload' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,docx'],
        ], attributes: [
            'employeeId' => __('employee'), 'leaveTypeId' => __('leave type'), 'fromDate' => __('first day'),
            'toDate' => __('last day'), 'delegateeId' => __('delegatee'), 'upload' => __('attachment'),
        ]);

        [$employee, $type] = [$this->employee(), $this->type()];
        if ($employee === null) {
            $this->addError('employeeId', __('Choose an employee from the list.'));

            return;
        }
        if ($type === null) {
            $this->addError('leaveTypeId', __('Choose a leave type from the list.'));

            return;
        }

        $existing = $this->applicationId ? $this->find($this->applicationId) : null;
        $check = $action->evaluate($employee, $type, $this->values(), $existing, byHr: ! $this->self, newAttachment: $this->upload !== null);
        if (! $check->ok()) {
            foreach ($check->problems as $problem) {
                $this->addError(self::FIELDS[$problem->field] ?? 'fromDate', $problem->message);
            }

            return;
        }

        try {
            $application = $action->handle($employee, $type, $this->values(), $check, $existing, $this->upload);
        } catch (WorkflowException $e) {
            $this->addError('reason', $e->getMessage());

            return;
        }

        Flux::modal('leave-form')->close();
        $this->dispatch('leave-saved');
        $this->dispatch('toast', variant: 'success', message: __(':no sent for approval.', ['no' => $application->number()]));
    }

    /** @return array<string, mixed> */
    private function values(): array
    {
        return [
            'from_date' => $this->fromDate,
            'to_date' => $this->isHalfDay ? $this->fromDate : $this->toDate,
            'is_half_day' => $this->isHalfDay,
            'delegatee_id' => $this->delegateeId,
            'contact_during_leave' => $this->contact,
            'reason' => $this->reason,
        ];
    }

    /** The employee: mine (ESS), or one the HR user can see. */
    private function employee(): ?Employee
    {
        if ($this->employeeId === '') {
            return null;
        }
        $employee = $this->self ? $this->mine() : Employee::query()->find((int) $this->employeeId);

        return $employee !== null && $employee->id === (int) $this->employeeId ? $employee : null;
    }

    /** The chosen type, only if it is one this form offers. */
    private function type(): ?LeaveType
    {
        $employee = $this->employee();

        return $employee && array_key_exists((int) $this->leaveTypeId, $this->typeOptions($employee))
            ? LeaveType::find((int) $this->leaveTypeId)
            : null;
    }

    /**
     * The leave types of the employee's profile (ESS: without HR-only ones), plus the type of the application
     * being changed.
     *
     * @return array<int, string>
     */
    private function typeOptions(Employee $employee): array
    {
        $ids = $employee->leaveProfile?->items()->pluck('leave_type_id')->all() ?? [];
        $current = $this->applicationId ? LeaveApplication::query()->whereKey($this->applicationId)->value('leave_type_id') : null;

        return LeaveType::query()
            ->where(fn ($q) => $q->where(fn ($q) => $q->whereIn('id', $ids)->where('is_active', true)
                ->when($this->self, fn ($q) => $q->where('hr_only', false)))
                ->orWhere('id', $current))
            ->ordered()->get()
            ->mapWithKeys(fn (LeaveType $t) => [$t->id => $t->optionLabel()])->all();
    }

    /** What the rules say about the form as it is now: shown under the dates. */
    private function preview(?Employee $employee): ?LeaveCheck
    {
        $type = $this->type();
        if ($employee === null || $type === null || $this->fromDate === '' || $this->toDate === '') {
            return null;
        }
        $existing = $this->applicationId ? LeaveApplication::find($this->applicationId) : null;

        return app(ApplyForLeave::class)->evaluate($employee, $type, $this->values(), $existing, byHr: ! $this->self, newAttachment: $this->upload !== null);
    }

    private function authorizeForm(): void
    {
        if (! $this->self) {
            Gate::authorize('hrm.leave.manage');
        }
    }

    private function find(int $id): LeaveApplication
    {
        $employeeIds = $this->self ? [$this->mine()?->id] : Employee::query()->select('id');

        return LeaveApplication::query()->whereIn('employee_id', $employeeIds)->findOrFail($id);
    }

    private function mine(): ?Employee
    {
        return Employee::query()->withoutGlobalScope(VisibleToCurrentUser::class)->where('user_id', Auth::id())->first();
    }

    public function render(): View
    {
        $employee = $this->employee();
        $type = $this->type();

        return view('hrm::livewire.leave.leave-application-form', [
            'employees' => $this->self ? [] : Employee::query()->active()->orderBy('code')->get(['id', 'code', 'first_name', 'last_name'])
                ->mapWithKeys(fn (Employee $e) => [$e->id => $e->code.' · '.$e->name])->all(),
            'types' => $employee ? $this->typeOptions($employee) : [],
            // Anyone active may cover my work, also outside the org units I can see.
            'delegatees' => Employee::query()->withoutGlobalScope(VisibleToCurrentUser::class)->active()
                ->whereKeyNot($employee?->id)->orderBy('first_name')->get(['id', 'code', 'first_name', 'last_name'])
                ->mapWithKeys(fn (Employee $e) => [$e->id => $e->name.' ('.$e->code.')'])->all(),
            'balance' => $employee && $type && $this->fromDate !== ''
                ? app(LeaveLedger::class)->balance($employee, $type, LeaveYear::containing($this->fromDate))
                : null,
            'leaveType' => $type,
            'preview' => $this->preview($employee),
            'existing' => $this->applicationId ? LeaveApplication::find($this->applicationId) : null,
        ]);
    }
}
