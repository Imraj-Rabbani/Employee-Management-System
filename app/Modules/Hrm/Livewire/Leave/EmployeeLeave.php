<?php

namespace App\Modules\Hrm\Livewire\Leave;

use App\Modules\Hrm\Data\LeaveYear;
use App\Modules\Hrm\Enums\LeaveEntryType;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\LeaveBalance;
use App\Modules\Hrm\Models\LeaveLedgerEntry;
use App\Modules\Hrm\Models\LeaveType;
use App\Modules\Hrm\Services\LeaveLedger;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Profile → Leave: the employee's balances in one leave year, and the ledger rows behind a balance. [LV-03]
 * HR with hrm.leave.balance adds an opening balance or a correction ("Add entry"). Everything is written
 * through LeaveLedger.
 *
 * Seeing it needs EmployeePolicy::view (who may open the profile).
 */
class EmployeeLeave extends Component
{
    #[Locked]
    public int $employeeId;

    /** The leave year shown, in the URL (?tab=leave&year=2026). */
    #[Url]
    public ?int $year = null;

    /** The leave type whose rows are open below the balances. */
    public ?int $typeId = null;

    /** @var array{leave_type_id: string, kind: string, days: string, date: string, note: string} */
    public array $form = ['leave_type_id' => '', 'kind' => 'opening', 'days' => '', 'date' => '', 'note' => ''];

    public function mount(Employee $employee): void
    {
        $this->employeeId = $employee->id;
        $this->year ??= LeaveYear::current()->year;
    }

    /** Opens (or closes, when clicked again) the rows of one leave type. */
    public function show(int $typeId): void
    {
        $this->typeId = $this->typeId === $typeId ? null : $typeId;
    }

    public function open(?int $typeId = null): void
    {
        $this->authorizeEntry();
        $this->form = [
            'leave_type_id' => $typeId ? (string) $typeId : '',
            'kind' => 'opening',
            'days' => '',
            'date' => $this->leaveYear()->start()->toDateString(),
            'note' => '',
        ];
        $this->resetValidation();

        Flux::modal('leave-entry')->show();
    }

    public function save(LeaveLedger $ledger): void
    {
        $employee = $this->authorizeEntry();
        $year = $this->leaveYear();
        $opening = $this->form['kind'] === 'opening';

        $this->validate([
            'form.leave_type_id' => ['required', 'integer', Rule::exists(LeaveType::class, 'id')],
            'form.kind' => ['required', 'in:opening,adjustment'],
            // An opening balance adds days; a correction may also take them away (minus).
            'form.days' => ['required', 'numeric', 'between:-999,999', 'not_in:0', 'decimal:0,2', ...($opening ? ['gt:0'] : [])],
            'form.date' => ['required', 'date'],
            'form.note' => [$opening ? 'nullable' : 'required', 'string', 'max:500'],
        ], attributes: ['form.leave_type_id' => __('leave type'), 'form.days' => __('days'), 'form.note' => __('note')]);

        $type = LeaveType::findOrFail((int) $this->form['leave_type_id']);
        $date = CarbonImmutable::parse($this->form['date']);

        if ($opening) {
            // One opening balance per year: a second one would be added on top. Changes are corrections.
            $exists = LeaveLedgerEntry::query()->where('employee_id', $employee->id)->where('leave_type_id', $type->id)
                ->where('year', $year->year)->where('entry_type', LeaveEntryType::Opening)->exists();
            if ($exists) {
                $this->addError('form.kind', __('There is already an opening balance for :type in :year. Use a correction to change it.',
                    ['type' => $type->name, 'year' => $year->label()]));

                return;
            }
            $ledger->credit($employee, $type, $year, (float) $this->form['days'], LeaveEntryType::Opening, $date, note: $this->form['note'] ?: null);
        } else {
            $ledger->adjust($employee, $type, $year, (float) $this->form['days'], $this->form['note'], $date);
        }

        Flux::modal('leave-entry')->close();
        $this->typeId = $type->id;
        $this->dispatch('toast', message: __('Leave balance saved.'), variant: 'success');
    }

    /** 404 outside your scope; 403 without view rights or without hrm.leave.balance. */
    private function authorizeEntry(): Employee
    {
        $employee = $this->employee();
        Gate::authorize('hrm.leave.balance');

        return $employee;
    }

    private function employee(): Employee
    {
        $employee = Employee::findOrFail($this->employeeId);
        Gate::authorize('view', $employee);

        return $employee;
    }

    private function leaveYear(): LeaveYear
    {
        return LeaveYear::of($this->year ?? LeaveYear::current()->year);
    }

    public function render(): View
    {
        $employee = $this->employee()->load('leaveProfile.items');
        $year = $this->leaveYear();

        $balances = LeaveBalance::query()->where('employee_id', $employee->id)->where('year', $year->year)->get()->keyBy('leave_type_id');
        $perYear = $employee->leaveProfile?->items->pluck('days_per_year', 'leave_type_id') ?? collect();

        // The types of the profile, plus any type that has rows this year (e.g. after the profile changed).
        $types = LeaveType::query()->whereIn('id', [...$perYear->keys(), ...$balances->keys()])->ordered()->get();

        $current = LeaveYear::current();
        $years = collect(range($current->year - 3, $current->year + 1))
            ->merge(LeaveLedgerEntry::query()->where('employee_id', $employee->id)->distinct()->pluck('year'))
            ->unique()->sortDesc()
            ->mapWithKeys(fn (int $y) => [$y => LeaveYear::of($y)->label()]);

        return view('hrm::livewire.leave.employee-leave', [
            'employee' => $employee,
            'leaveYear' => $year,
            'years' => $years->all(),
            'types' => $types,
            'balances' => $balances,
            'perYear' => $perYear,
            'entries' => $this->typeId === null ? collect() : LeaveLedgerEntry::query()
                ->where('employee_id', $employee->id)->where('leave_type_id', $this->typeId)->where('year', $year->year)
                ->orderBy('entry_date')->orderBy('id')->get(),
            'canEnter' => Gate::allows('hrm.leave.balance'),
            'typeOptions' => LeaveType::where('is_active', true)->orWhereIn('id', $types->pluck('id'))->ordered()->get()
                ->mapWithKeys(fn (LeaveType $t) => [$t->id => $t->optionLabel()])->all(),
        ]);
    }
}
