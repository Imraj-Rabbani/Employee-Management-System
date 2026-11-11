<?php

namespace App\Modules\Workflow\Models;

use App\Core\Models\TenantModel;
use App\Modules\Workflow\Enums\ApprovalStatus;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Carbon;

/**
 * One document waiting at (or answered at) one level (legacy `approvals`). [WF-03]
 *
 *     $approval->approvable        the document: a LeaveApplication, Loan ... (polymorphic)
 *     Approval::pending()->get()   query scope: rows nobody has answered yet
 *
 * @property int $id
 * @property string $approvable_type
 * @property int $approvable_id
 * @property int $workflow_step_id
 * @property int $level
 * @property int|null $requester_employee_id
 * @property int|null $requester_user_id central users.id: who submitted
 * @property int|null $approver_employee_id
 * @property int|null $approver_user_id central users.id: who must answer
 * @property ApprovalStatus $status
 * @property int|null $responded_by central users.id
 * @property Carbon|null $responded_at
 * @property string|null $comment
 * @property string|null $attachment_path
 * @property string|null $amount entered by the approver when the document asks for one (7.7), e.g. hours
 * @property Carbon|null $created_at when the approver was asked
 * @property Carbon|null $updated_at
 */
class Approval extends TenantModel
{
    protected $fillable = [
        'workflow_step_id', 'level', 'requester_employee_id', 'requester_user_id', 'approver_employee_id', 'approver_user_id',
        'status', 'responded_by', 'responded_at', 'comment', 'amount', 'attachment_path',
    ];

    protected $attributes = ['status' => 'pending'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'status' => ApprovalStatus::class,
            'amount' => 'decimal:2',
            'responded_at' => 'datetime',
        ];
    }

    /**
     * The document this approval is for. Laravel reads approvable_type (which class)
     * and approvable_id (which row) and loads the right model.
     *
     * @return MorphTo<Model, $this>
     */
    public function approvable(): MorphTo
    {
        return $this->morphTo();
    }

    /** @return BelongsTo<WorkflowStep, $this> */
    public function step(): BelongsTo
    {
        return $this->belongsTo(WorkflowStep::class, 'workflow_step_id');
    }

    /** @param  Builder<self>  $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', ApprovalStatus::Pending);
    }
}
