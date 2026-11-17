<?php

namespace App\Modules\Workflow\Livewire;

use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Models\EmploymentType;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Hrm\Models\EntityType;
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
 * New / Edit workflow with its levels (modal "workflow-form").
 *
 * Levels are an array in the form: the first row is level 1, the second level 2 ...
 * On save, level N keeps the database row of the old level N (so approval history keeps
 * pointing at it); extra old levels are removed, which is refused if they have history.
 *
 * Scope: entity (the document must be in it, e.g. Department "Finance") and employment type,
 * and a minimum amount.
 *
 * Approvers per level: anyone with a role, or the head of the document's entity of one type
 * ("Head of entity" + Department → the head of the employee's Department). Supervisor / specific
 * employee have resolvers but are not offered here yet.
 */
class WorkflowForm extends Component
{
    /** Approver types that can be chosen in this form (the others show "soon"). */
    private const OFFERED = ['role', 'entity_head'];

    #[Locked]
    public ?int $workflowId = null;

    public string $approvalTypeId = '';

    public string $name = '';

    public string $minAmount = '';

    public string $scopeEntityId = '';

    public string $scopeEmploymentTypeId = '';

    public bool $sendEmail = true;

    public bool $allowReview = true;

    public bool $allowReject = true;

    public bool $isActive = true;

    /** @var list<array{approver_type: string, approver_role_id: string, approver_entity_type_id: string, attachment_required: bool}> */
    public array $steps = [];

    #[On('create-workflow')]
    public function create(): void
    {
        $this->reset();
        $this->resetValidation();
        $this->addStep();
        Flux::modal('workflow-form')->show();
    }

    #[On('edit-workflow')]
    public function edit(int $id): void
    {
        $workflow = Workflow::with('steps')->findOrFail($id);

        $this->resetValidation();
        $this->workflowId = $workflow->id;
        $this->approvalTypeId = (string) $workflow->approval_type_id;
        $this->name = $workflow->name;
        $this->minAmount = $workflow->min_amount ?? '';
        $this->scopeEntityId = (string) $workflow->scope_entity_id;
        $this->scopeEmploymentTypeId = (string) $workflow->scope_employment_type_id;
        $this->sendEmail = $workflow->send_email;
        $this->allowReview = $workflow->allow_review;
        $this->allowReject = $workflow->allow_reject;
        $this->isActive = $workflow->is_active;
        $this->steps = $workflow->steps->map(fn (WorkflowStep $s) => [
            'approver_type' => $s->approver_type->value,
            'approver_role_id' => (string) $s->approver_role_id,
            'approver_entity_type_id' => (string) $s->approver_entity_type_id,
            'attachment_required' => $s->attachment_required,
        ])->values()->all();

        Flux::modal('workflow-form')->show();
    }

    public function addStep(): void
    {
        $this->steps[] = ['approver_type' => ApproverType::Role->value, 'approver_role_id' => '', 'approver_entity_type_id' => '', 'attachment_required' => false];
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

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'approvalTypeId' => ['required', Rule::exists(ApprovalType::class, 'id')],
            'name' => ['required', 'string', 'max:100'],
            'minAmount' => ['nullable', 'numeric', 'min:0', 'max:9999999999999'],
            'scopeEntityId' => ['nullable', Rule::exists(Entity::class, 'id')],
            'scopeEmploymentTypeId' => ['nullable', Rule::exists(EmploymentType::class, 'id')],
            'sendEmail' => ['boolean'],
            'allowReview' => ['boolean'],
            'allowReject' => ['boolean'],
            'isActive' => ['boolean'],
            'steps' => ['required', 'array', 'min:1', 'max:10'],
            'steps.*.approver_type' => ['required', Rule::in(self::OFFERED)],
            'steps.*.approver_role_id' => ['exclude_unless:steps.*.approver_type,'.ApproverType::Role->value, 'required', Rule::exists(Role::class, 'id')],
            'steps.*.approver_entity_type_id' => ['exclude_unless:steps.*.approver_type,'.ApproverType::EntityHead->value, 'required', Rule::exists(EntityType::class, 'id')],
            'steps.*.attachment_required' => ['boolean'],
        ];
    }

    /** @return array<string, string> */
    protected function validationAttributes(): array
    {
        return [
            'approvalTypeId' => __('type'),
            'minAmount' => __('minimum amount'),
            'scopeEntityId' => __('entity'),
            'scopeEmploymentTypeId' => __('employment type'),
            'steps' => __('levels'),
            'steps.*.approver_role_id' => __('role'),
            'steps.*.approver_entity_type_id' => __('entity type'),
            'steps.*.approver_type' => __('approver'),
        ];
    }

    public function save(): void
    {
        $this->validate();

        $workflow = $this->workflowId ? Workflow::with('steps')->findOrFail($this->workflowId) : new Workflow;

        // Levels that would disappear must not have approval history.
        $removed = $workflow->exists ? $workflow->steps->slice(count($this->steps)) : collect();
        $used = $removed->first(fn (WorkflowStep $s) => $s->approvals()->exists());
        if ($used) {
            $this->addError('steps', __('Level :level has approval history and cannot be removed. Make this workflow inactive and create a new one instead.', ['level' => $used->level]));

            return;
        }

        DB::connection('tenant')->transaction(function () use ($workflow, $removed) {
            $workflow->fill([
                'approval_type_id' => (int) $this->approvalTypeId,
                'name' => $this->name,
                'min_amount' => $this->minAmount === '' ? null : $this->minAmount,
                'scope_entity_id' => $this->scopeEntityId === '' ? null : (int) $this->scopeEntityId,
                'scope_employment_type_id' => $this->scopeEmploymentTypeId === '' ? null : (int) $this->scopeEmploymentTypeId,
                'send_email' => $this->sendEmail,
                'allow_review' => $this->allowReview,
                'allow_reject' => $this->allowReject,
                'is_active' => $this->isActive,
            ])->save();

            $removed->each(fn (WorkflowStep $s) => $s->delete());

            foreach ($this->steps as $i => $step) {
                WorkflowStep::updateOrCreate(
                    ['workflow_id' => $workflow->id, 'level' => $i + 1],
                    [
                        'approver_type' => $step['approver_type'],
                        // Only the field of the chosen approver type is kept.
                        'approver_role_id' => $step['approver_type'] === ApproverType::Role->value ? (int) $step['approver_role_id'] : null,
                        'approver_entity_type_id' => $step['approver_type'] === ApproverType::EntityHead->value ? (int) $step['approver_entity_type_id'] : null,
                        'approver_employee_id' => null,
                        'attachment_required' => (bool) $step['attachment_required'],
                    ],
                );
            }
        });

        Flux::modal('workflow-form')->close();
        $this->dispatch('workflow-saved');
        $this->dispatch('toast', message: __(':name saved.', ['name' => $workflow->name]), variant: 'success');
    }

    public function render(): View
    {
        return view('workflow::livewire.workflow-form', [
            'types' => ApprovalType::orderBy('name')->pluck('name', 'id')->all(),
            'roles' => Role::orderBy('name')->pluck('name', 'id')->all(),
            'entities' => Entity::options(),
            'entityTypes' => EntityType::ordered()->pluck('name', 'id')->all(),
            'employmentTypes' => EmploymentType::where('is_active', true)->orderBy('name')->pluck('name', 'id')->all(),
            'approverTypes' => ApproverType::cases(),
            'offered' => self::OFFERED,
        ]);
    }
}
