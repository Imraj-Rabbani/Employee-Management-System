<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Enums\InterviewStatus;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A stage of a job posting's interviews, e.g. "Stage 1" or "Technical round", with the question set its
 * interviewers score. Made the first time HR schedules interviews with a new stage name; later
 * interviews can be put in the same stage. Its question set never changes (fair comparison).
 * An application is in a stage at most once (a cancelled interview does not count).
 *
 * @property int $id
 * @property int $job_posting_id
 * @property string $name
 * @property int $question_set_id
 * @property int|null $created_by_user_id
 */
class InterviewStage extends TenantModel
{
    protected $fillable = ['job_posting_id', 'name', 'question_set_id', 'created_by_user_id'];

    /** @return BelongsTo<JobPosting, $this> */
    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    /** @return BelongsTo<QuestionSet, $this> */
    public function questionSet(): BelongsTo
    {
        return $this->belongsTo(QuestionSet::class);
    }

    /** @return HasMany<Interview, $this> */
    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class);
    }

    /** Is this application already in the stage (an interview that was not cancelled)? */
    public function has(Application $application): bool
    {
        return $this->interviews()->where('application_id', $application->id)
            ->where('status', '!=', InterviewStatus::Cancelled)->exists();
    }
}
