<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Models\User;
use App\Modules\Hrm\Enums\ApplicationStage;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One move of an application through the pipeline: from → to, who, when, why. [REC-04]
 * Written only by ApplyToPosting (the first row) and MoveApplication. Never edited (no updated_at).
 *
 * @property int $id
 * @property int $application_id
 * @property ApplicationStage|null $from_stage null = the application was created
 * @property ApplicationStage $to_stage
 * @property int|null $rejection_reason_id
 * @property string|null $note
 * @property int|null $changed_by_user_id
 * @property Carbon $created_at
 */
class ApplicationStageChange extends TenantModel
{
    /** Only created_at: Eloquent fills it, and skips updated_at. */
    public const UPDATED_AT = null;

    protected $fillable = ['application_id', 'from_stage', 'to_stage', 'rejection_reason_id', 'note', 'changed_by_user_id'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['from_stage' => ApplicationStage::class, 'to_stage' => ApplicationStage::class];
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return BelongsTo<RejectionReason, $this> */
    public function rejectionReason(): BelongsTo
    {
        return $this->belongsTo(RejectionReason::class);
    }

    /** The login who made the move (central database, so no real foreign key). */
    public function changedBy(): ?User
    {
        return $this->changed_by_user_id === null ? null : User::find($this->changed_by_user_id);
    }
}
