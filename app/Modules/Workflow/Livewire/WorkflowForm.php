<?php

namespace App\Modules\Workflow\Livewire;

use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Hrm\Models\EntityType;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Workflow\Enums\ApproverType;
use App\Modules\Workflow\Models\ApprovalType;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowStep;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * Configure the workflow of ONE approval type, e.g. Leave (modal "workflow-form").
 * Opened from the Workflow Setup list with the approval type's id; the type's workflow row is
 * created on the first save.
 *
 * Levels are an array in the form: the first row is level 1, the second level 2 ... Each level has
 *   Type                approver_type (ApproverType)
 *   Entity type         for "Employee's Entity Head" and "Specific Entity Head", e.g. Branch
 *   Specific entity     for "Specific Entity Head", e.g. Branch "Gulshan" (only entities of the chosen type)
 *   Specific employee   for "Specific People" (employees with a login: they answer in the app)
 * One level is enough; more are optional.
 *
 * On save, level N keeps the database row of the old level N (so approval history keeps pointing
 * at it); extra old levels are removed, which is refused if they have history.
 */
class WorkflowForm extends Component
{
    #[Locked]
    public ?int $approvalTypeId = null;

    #[Locked]
    public string $typeName = '';

    public bool $sendEmail = true;

    public bool $allowReview = true;

    public bool $allowReject = true;

    public bool $isActive = true;

    /** @var list<array{approver_type: string, approver_entity_type_id: string, approver_entity_id: string, approver_employee_id: string, attachment_required: bool}> */
    public array $steps = [];

    /** $id = the APPROVAL TYPE's id (one workflow per type). */
    #[On('edit-workflow')]
    public function edit(int $id): void
    {
        $type = ApprovalType::with('workflow.steps')->where('is_active', true)->findOrFail($id);
        $workflow = $type->workflow;

        $this->reset();
        $this->resetValidation();
        $this->approvalTypeId = $type->id;
        $this->typeName = $type->name;

        if ($workflow !== null) {
            $this->sendEmail = $workflow->send_email;
            $this->allowReview = $workflow->allow_review;
            $this->allowReject = $workflow->allow_reject;
            $this->isActive = $workflow->is_active;
            $this->steps = $workflow->steps->map(fn (WorkflowStep $s) => [
                'approver_type' => $s->approver_type->value,
                'approver_entity_type_id' => (string) $s->approver_entity_type_id,
                'approver_entity_id' => (string) $s->approver_entity_id,
                'approver_employee_id' => (string) $s->approver_employee_id,
                'attachment_required' => $s->attachment_required,
            ])->values()->all();
        }

        if ($this->steps === []) {
            $this->addStep();
        }

        Flux::modal('workflow-form')->show();
    }

    public function addStep(): void
    {
        $this->steps[] = [
            'approver_type' => ApproverType::Supervisor->value,
            'approver_entity_type_id' => '',
            'approver_entity_id' => '',
            'approver_employee_id' => '',
            'attachment_required' => false,
        ];
    }

    public function removeStep(int $index): void
    {
        unset($this->steps[$index]);
        $this->steps = array_values($this->steps);
    }

    public function moveUp(int $index): void
    {
        if ($index > 0 && isset($this->steps[$index])) {
            [$this->steps[$index - 1], $this->steps[$index]] = [$this->steps[$index], $this->steps[$index - 1]];
        }
    }

    /** A new entity type on a level empties its specific entity: that one belongs to the old type. */
    public function updated(string $property): void
    {
        if (preg_match('/^steps\.(\d+)\.approver_entity_type_id$/', $property, $m) === 1) {
            $this->steps[(int) $m[1]]['approver_entity_id'] = '';
        }
    }

    /**
     * Built per level, because the specific entity must be of THAT level's entity type.
     *
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $rules = [
            'sendEmail' => ['boolean'],
            'allowReview' => ['boolean'],
            'allowReject' => ['boolean'],
            'isActive' => ['boolean'],
            'steps' => ['required', 'array', 'min:1', 'max:10'],
        ];

        foreach ($this->steps as $i => $step) {
            $type = ApproverType::tryFrom($step['approver_type']);

            $rules["steps.$i.approver_type"] = ['required', Rule::enum(ApproverType::class)];
            $rules["steps.$i.attachment_required"] = ['boolean'];

            if ($type?->needsEntityType()) {
                $rules["steps.$i.approver_entity_type_id"] = ['required', Rule::exists(EntityType::class, 'id')];
            }
            if ($type === ApproverType::SpecificEntityHead) {
                $rules["steps.$i.approver_entity_id"] = ['required', Rule::exists(Entity::class, 'id')
                    ->where('entity_type_id', (int) $step['approver_entity_type_id'])];
            }
            if ($type === ApproverType::Employee) {
                // Must have a login: approvals are answered in the app (or by the emailed link).
                $rules["steps.$i.approver_employee_id"] = ['required', Rule::exists(Employee::class, 'id')->whereNotNull('user_id')];
            }
        }

        return $rules;
    }

    /** @return array<string, string> */
    protected function messages(): array
    {
        return ['steps.*.approver_employee_id.exists' => __('Choose an employee who has a login.')];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'steps' => __('levels'),
            'steps.*.approver_type' => __('type'),
            'steps.*.approver_entity_type_id' => __('entity type'),
            'steps.*.approver_entity_id' => __('specific entity'),
            'steps.*.approver_employee_id' => __('specific employee'),
        ];
    }

    public function save(): void
    {
        $this->validate();

        $type = ApprovalType::where('is_active', true)->findOrFail($this->approvalTypeId);
        $workflow = Workflow::with('steps')->firstOrNew(['approval_type_id' => $type->id]);

        // Levels that would disappear must not have approval history.
        $removed = $workflow->exists ? $workflow->steps->slice(count($this->steps)) : collect();
        $used = $removed->first(fn (WorkflowStep $s) => $s->approvals()->exists());
        if ($used) {
            $this->addError('steps', __('Level :level has approval history and cannot be removed. Change who approves it instead.', ['level' => $used->level]));

            return;
        }

        DB::connection('tenant')->transaction(function () use ($workflow, $removed) {
            $workflow->fill([
                'send_email' => $this->sendEmail,
                'allow_review' => $this->allowReview,
                'allow_reject' => $this->allowReject,
                'is_active' => $this->isActive,
            ])->save();

            $removed->each(fn (WorkflowStep $s) => $s->delete());

            foreach ($this->steps as $i => $step) {
                $type = ApproverType::from($step['approver_type']);

                WorkflowStep::updateOrCreate(
                    ['workflow_id' => $workflow->id, 'level' => $i + 1],
                    [
                        'approver_type' => $type,
                        // Only the fields of the chosen type are kept.
                        'approver_entity_type_id' => $type->needsEntityType() ? (int) $step['approver_entity_type_id'] : null,
                        'approver_entity_id' => $type === ApproverType::SpecificEntityHead ? (int) $step['approver_entity_id'] : null,
                        'approver_employee_id' => $type === ApproverType::Employee ? (int) $step['approver_employee_id'] : null,
                        'attachment_required' => (bool) $step['attachment_required'],
                    ],
                );
            }
        });

        Flux::modal('workflow-form')->close();
        $this->dispatch('workflow-saved');
        $this->dispatch('toast', message: __(':name workflow saved.', ['name' => $type->name]), variant: 'success');
    }

    public function render(): View
    {
        // Employees who can approve: active with a login, plus anyone already chosen (so an old choice still shows).
        $chosen = array_values(array_filter(array_map(fn (array $s) => (int) $s['approver_employee_id'], $this->steps)));

        return view('workflow::livewire.workflow-form', [
            'approverTypes' => ApproverType::cases(),
            'entityTypes' => EntityType::ordered()->pluck('name', 'id')->all(),
            'entitiesByType' => Entity::optionsByType(array_values(array_filter(array_map(fn (array $s) => (int) $s['approver_entity_id'], $this->steps)))),
            'employees' => Employee::withoutGlobalScope(VisibleToCurrentUser::class)
                ->where(fn ($q) => $q->where(fn ($a) => $a->active()->whereNotNull('user_id'))->orWhereIn('id', $chosen))
                ->orderBy('first_name')->orderBy('last_name')
                ->get(['id', 'code', 'first_name', 'last_name'])
                ->mapWithKeys(fn (Employee $e) => [$e->id => $e->name.' ('.$e->code.')'])
                ->all(),
        ]);
    }
}
