<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One employee on one interview's panel, with their score sheet. [REC-05]
 *
 * @property int $id
 * @property int $interview_id
 * @property int $employee_id
 * @property string|null $recommendation
 * @property string|null $comment
 * @property Carbon|null $submitted_at null = not sent yet (still editable)
 */
class InterviewPanelist extends TenantModel
{
    protected $fillable = ['interview_id', 'employee_id', 'recommendation', 'comment'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['submitted_at' => 'datetime'];
    }

    /** @return BelongsTo<Interview, $this> */
    public function interview(): BelongsTo
    {
        return $this->belongsTo(Interview::class);
    }

    /**
     * withoutGlobalScope: HR must see who is on the panel even when the interviewer works in an org unit
     * outside HR's own access (e.g. a department head from another division).
     *
     * @return BelongsTo<Employee, $this>
     */
    public function employee(): BelongsTo
    {
        return $this->belongsTo(Employee::class)->withoutGlobalScope(VisibleToCurrentUser::class);
    }

    /** @return HasMany<InterviewScore, $this> */
    public function scores(): HasMany
    {
        return $this->hasMany(InterviewScore::class);
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }
}
