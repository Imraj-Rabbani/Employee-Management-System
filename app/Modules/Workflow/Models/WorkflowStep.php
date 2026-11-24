<?php

namespace App\Modules\Workflow\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Hrm\Models\EntityType;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Workflow\Enums\ApproverType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One level of a workflow and who approves it (legacy `workflows` level rows).
 *
 * Which column is filled depends on the approver type:
 *   supervisor            nothing
 *   entity_head           approver_entity_type_id   (e.g. Branch: the employee's branch head)
 *   specific_entity_head  approver_entity_type_id + approver_entity_id   (e.g. Branch "Gulshan")
 *   employee              approver_employee_id
 *
 * @property int $id
 * @property int $workflow_id
 * @property int $level
 * @property ApproverType $approver_type
 * @property int|null $approver_entity_type_id
 * @property int|null $approver_entity_id
 * @property int|null $approver_employee_id
 * @property bool $attachment_required
 * @property list<int>|null $representatives employee ids who may answer instead
 */
class WorkflowStep extends TenantModel
{
    protected $fillable = [
        'workflow_id', 'level', 'approver_type', 'approver_entity_type_id', 'approver_entity_id', 'approver_employee_id',
        'attachment_required', 'representatives',
    ];

    protected $attributes = ['attachment_required' => false];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'approver_type' => ApproverType::class,   // DB string ⇄ enum
            'approver_entity_type_id' => 'integer',
            'approver_entity_id' => 'integer',
            'approver_employee_id' => 'integer',
            'attachment_required' => 'boolean',
            'representatives' => 'array',             // DB JSON ⇄ PHP array
        ];
    }

    /** @return BelongsTo<Workflow, $this> */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /** @return BelongsTo<EntityType, $this> */
    public function approverEntityType(): BelongsTo
    {
        return $this->belongsTo(EntityType::class, 'approver_entity_type_id');
    }

    /** @return BelongsTo<Entity, $this> */
    public function approverEntity(): BelongsTo
    {
        return $this->belongsTo(Entity::class, 'approver_entity_id');
    }

    /**
     * withoutGlobalScope: the setup screen must show the chosen person even when the viewer's
     * role cannot see that employee.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function approverEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'approver_employee_id')->withoutGlobalScope(VisibleToCurrentUser::class);
    }

    /** @return HasMany<Approval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }

    /**
     * Who approves, in words: "Reporting supervisor", "Head of Branch", "Head of Gulshan (Branch)", "Rahim Uddin".
     * Load approverEntityType / approverEntity / approverEmployee first in lists.
     */
    public function describe(): string
    {
        $type = $this->approverEntityType->name ?? '?';

        return match ($this->approver_type) {
            ApproverType::Supervisor => __('Reporting supervisor'),
            ApproverType::EntityHead => __('Head of :type', ['type' => $type]),
            ApproverType::SpecificEntityHead => __('Head of :entity (:type)', ['entity' => $this->approverEntity->name ?? '?', 'type' => $type]),
            ApproverType::Employee => $this->approverEmployee->name ?? __('nobody chosen'),
        };
    }
}
