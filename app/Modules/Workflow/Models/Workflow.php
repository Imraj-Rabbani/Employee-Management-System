<?php

namespace App\Modules\Workflow\Models;

use App\Core\Models\TenantModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * The approval workflow of ONE approval type (e.g. Leave): its settings and its levels.
 * Each type has at most one (workflows.approval_type_id is unique); it is made the first time
 * someone configures the type on Workflow → Workflow Setup.
 *
 *     ApprovalType::forKey('leave')->workflow?->steps
 *
 * @property int $id
 * @property int $approval_type_id
 * @property bool $send_email
 * @property bool $allow_review
 * @property bool $allow_reject
 * @property bool $is_active
 */
class Workflow extends TenantModel
{
    protected $fillable = ['approval_type_id', 'send_email', 'allow_review', 'allow_reject', 'is_active'];

    protected $attributes = ['send_email' => true, 'allow_review' => true, 'allow_reject' => true, 'is_active' => true];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'send_email' => 'boolean',
            'allow_review' => 'boolean',
            'allow_reject' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /** @return BelongsTo<ApprovalType, $this> */
    public function approvalType(): BelongsTo
    {
        return $this->belongsTo(ApprovalType::class);
    }

    /**
     * Steps in level order: 1, 2, 3 ...
     *
     * @return HasMany<WorkflowStep, $this>
     */
    public function steps(): HasMany
    {
        return $this->hasMany(WorkflowStep::class)->orderBy('level');
    }

    /** "1. Reporting supervisor → 2. Head of Branch → 3. Rahim Uddin" (see WorkflowStep::describe()). */
    public function levelsText(): string
    {
        return $this->steps->map(fn (WorkflowStep $step) => $step->level.'. '.$step->describe())->implode(' → ');
    }
}
