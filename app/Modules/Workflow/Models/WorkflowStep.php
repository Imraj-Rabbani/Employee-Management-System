<?php

namespace App\Modules\Workflow\Models;

use App\Core\Models\TenantModel;
use App\Modules\Core\Models\Role;
use App\Modules\Workflow\Enums\ApproverType;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One level of a workflow and who approves it (legacy `workflows` level rows). [WF-02]
 *
 * @property int $id
 * @property int $workflow_id
 * @property int $level
 * @property ApproverType $approver_type
 * @property int|null $approver_employee_id
 * @property int|null $approver_role_id
 * @property bool $attachment_required
 * @property list<int>|null $representatives employee ids who may answer instead
 */
class WorkflowStep extends TenantModel
{
    protected $fillable = [
        'workflow_id', 'level', 'approver_type', 'approver_employee_id', 'approver_role_id',
        'attachment_required', 'representatives',
    ];

    protected $attributes = ['attachment_required' => false];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'approver_type' => ApproverType::class,   // DB string ⇄ enum
            'attachment_required' => 'boolean',
            'representatives' => 'array',             // DB JSON ⇄ PHP array
        ];
    }

    /** @return BelongsTo<Workflow, $this> */
    public function workflow(): BelongsTo
    {
        return $this->belongsTo(Workflow::class);
    }

    /** @return BelongsTo<Role, $this> */
    public function approverRole(): BelongsTo
    {
        return $this->belongsTo(Role::class, 'approver_role_id');
    }

    /** @return HasMany<Approval, $this> */
    public function approvals(): HasMany
    {
        return $this->hasMany(Approval::class);
    }
}
