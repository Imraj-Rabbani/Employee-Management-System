<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Modules\Hrm\Enums\ManpowerRequestType;
use App\Modules\Hrm\Models\Designation;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\EmploymentType;
use App\Modules\Hrm\Models\Grade;
use App\Modules\Hrm\Models\ManpowerRequest;
use App\Modules\Hrm\Models\Position;
use App\Modules\Hrm\Services\EmployeeVisibility;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * New / Edit / Delete manpower request (modal "manpower-request-form"). [REC-01]
 *
 * Rules beyond the fields:
 *   - the org unit must be one the user can see (same list as everywhere: EmployeeVisibility)
 *   - only a draft or a request sent back for changes (Review) can be edited; only a draft deleted
 *   - Replacement needs the employee being replaced, from the chosen org unit
 *   - position (optional) must belong to the chosen org unit
 *
 * Not a LookupForm: those are for small setup lists. This form has its own rules and dependent dropdowns.
 */
class ManpowerRequestForm extends Component
{
    /** null = creating a new request */
    #[Locked]
    public ?int $recordId = null;

    /** @var array<string, mixed> */
    public array $form = [];

    #[On('create-manpower-request')]
    public function create(): void
    {
        $this->recordId = null;
        $this->form = [
            'org_unit_id' => '', 'designation_id' => '', 'position_id' => '', 'grade_id' => '', 'employment_type_id' => '',
            'type' => ManpowerRequestType::New->value, 'replaces_employee_id' => '', 'headcount' => 1,
            'salary_min' => '', 'salary_max' => '', 'needed_by' => '', 'justification' => '',
        ];
        $this->resetValidation();

        Flux::modal('manpower-request-form')->show();
    }

    #[On('edit-manpower-request')]
    public function edit(int $id): void
    {
        $request = $this->find($id);

        $this->recordId = $request->id;
        $this->form = [
            'org_unit_id' => $request->org_unit_id,
            'designation_id' => $request->designation_id,
            'position_id' => $request->position_id ?? '',
            'grade_id' => $request->grade_id ?? '',
            'employment_type_id' => $request->employment_type_id ?? '',
            'type' => $request->type->value,
            'replaces_employee_id' => $request->replaces_employee_id ?? '',
            'headcount' => $request->headcount,
            'salary_min' => $request->salary_min ?? '',
            'salary_max' => $request->salary_max ?? '',
            'needed_by' => $request->needed_by?->toDateString() ?? '',
            'justification' => $request->justification ?? '',
        ];
        $this->resetValidation();

        Flux::modal('manpower-request-form')->show();
    }

    /** Another org unit: the position and the person being replaced belonged to the old one. */
    public function updatedFormOrgUnitId(): void
    {
        $this->form['position_id'] = '';
        $this->form['replaces_employee_id'] = '';
    }

    public function save(): void
    {
        $request = $this->recordId ? $this->find($this->recordId) : null;
        if ($request && ! $request->isEditable()) {
            $this->addError('form.org_unit_id', __('This request is waiting for approval or already answered, so it cannot be changed.'));

            return;
        }

        $isReplacement = ($this->form['type'] ?? '') === ManpowerRequestType::Replacement->value;
        $unitId = (int) ($this->form['org_unit_id'] ?: 0);

        $values = $this->validate([
            'form.org_unit_id' => ['required', Rule::in(array_keys($this->orgUnits()))],
            'form.designation_id' => ['required', Rule::exists(Designation::class, 'id')],
            'form.position_id' => ['nullable', Rule::exists(Position::class, 'id')->where('org_unit_id', $unitId)],
            'form.grade_id' => ['nullable', Rule::exists(Grade::class, 'id')],
            'form.employment_type_id' => ['nullable', Rule::exists(EmploymentType::class, 'id')],
            'form.type' => ['required', Rule::enum(ManpowerRequestType::class)],
            'form.replaces_employee_id' => [Rule::requiredIf($isReplacement), 'nullable', Rule::in(array_keys($this->employees($unitId)))],
            'form.headcount' => ['required', 'integer', 'min:1', 'max:999'],
            'form.salary_min' => ['nullable', 'numeric', 'min:0', 'max:999999999'],
            'form.salary_max' => ['nullable', 'numeric', 'min:0', 'max:999999999', 'gte:form.salary_min'],
            // Not in the past for a NEW request; an older one sent back for changes may keep its date.
            'form.needed_by' => ['nullable', 'date', ...($request ? [] : ['after_or_equal:today'])],
            'form.justification' => ['nullable', 'string', 'max:2000'],
        ], attributes: [
            'form.org_unit_id' => __('org unit'), 'form.designation_id' => __('designation'),
            'form.replaces_employee_id' => __('employee being replaced'), 'form.salary_min' => __('minimum salary'),
            'form.salary_max' => __('maximum salary'), 'form.needed_by' => __('needed by'),
        ])['form'];

        $values = array_map(fn ($v) => $v === '' ? null : $v, $values);
        if (! $isReplacement) {
            $values['replaces_employee_id'] = null;   // switched back to "New position"
        }

        if ($request) {
            $request->update($values);
        } else {
            $request = ManpowerRequest::create([...$values, 'requested_by_user_id' => Auth::id()]);
        }

        $this->finish(__('Request :no saved.', ['no' => $request->number()]));
    }

    public function delete(): void
    {
        $request = $this->find((int) $this->recordId);
        if (! $request->isDraft()) {
            $this->addError('form.org_unit_id', __('Only a draft can be deleted. This one has an approval history.'));

            return;
        }

        $request->getConnection()->transaction(fn () => $request->delete());

        // Back to the list: also when deleting from the request's own page, which no longer exists.
        session()->flash('toast', ['message' => __('Request :no deleted.', ['no' => $request->number()]), 'variant' => 'success']);
        $this->redirectRoute('hrm.recruitment.requests', navigate: true);
    }

    /** Only requests the user may see: someone else's id in the URL / event gives 404. */
    private function find(int $id): ManpowerRequest
    {
        return ManpowerRequest::query()->visibleTo(Auth::user())->findOrFail($id);
    }

    private function finish(string $message): void
    {
        Flux::modal('manpower-request-form')->close();
        $this->dispatch('manpower-request-saved');
        $this->dispatch('toast', message: $message, variant: 'success');
    }

    /** @return array<int, string> */
    private function orgUnits(): array
    {
        return app(EmployeeVisibility::class)->orgUnitOptionsFor(Auth::user());
    }

    /**
     * Employees of the chosen unit who can be replaced (the visibility scope applies by itself).
     *
     * @return array<int, string>
     */
    private function employees(int $unitId): array
    {
        if ($unitId === 0) {
            return [];
        }

        return Employee::query()->where('org_unit_id', $unitId)->orderBy('first_name')->get()
            ->mapWithKeys(fn (Employee $e) => [$e->id => $e->code.' · '.$e->name])->all();
    }

    public function render(): View
    {
        $unitId = (int) ($this->form['org_unit_id'] ?? 0);
        $request = $this->recordId ? ManpowerRequest::query()->visibleTo(Auth::user())->find($this->recordId) : null;

        return view('hrm::livewire.recruitment.manpower-request-form', [
            'request' => $request,
            'editable' => $request === null || $request->isEditable(),
            'units' => $this->orgUnits(),
            'designations' => Designation::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'positions' => $unitId ? Position::where('org_unit_id', $unitId)->orderBy('name')->pluck('name', 'id')->all() : [],
            'grades' => Grade::ordered()->pluck('name', 'id')->all(),
            'types' => EmploymentType::orderBy('name')->pluck('name', 'id')->all(),
            'requestTypes' => ManpowerRequestType::options(),
            'employees' => $this->employees($unitId),
        ]);
    }
}
