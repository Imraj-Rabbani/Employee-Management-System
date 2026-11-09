<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Models\User;
use App\Modules\Hrm\Database\Factories\ApplicationFactory;
use App\Modules\Hrm\Enums\ApplicationStage;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One applicant applying to one job posting. [REC-03]
 * Created ONLY through Hrm\Actions\ApplyToPosting (it checks the posting takes applications and the
 * person has not applied to it already). Its stage moves through the pipeline in 6.5.
 *
 * @property int $id
 * @property int $applicant_id
 * @property int $job_posting_id
 * @property ApplicationStage $stage
 * @property Carbon $applied_on
 * @property int|null $created_by_user_id
 * @property Carbon $created_at
 */
#[UseFactory(ApplicationFactory::class)]
class Application extends TenantModel
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory;

    protected $fillable = ['applicant_id', 'job_posting_id', 'stage', 'applied_on', 'created_by_user_id'];

    protected $attributes = ['stage' => 'applied'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['stage' => ApplicationStage::class, 'applied_on' => 'date'];
    }

    /** @return BelongsTo<Applicant, $this> */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    /** @return BelongsTo<JobPosting, $this> */
    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    /**
     * Applications to postings the user can see (same org-unit limit as postings and requests).
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereHas('jobPosting', fn (Builder $q) => $q->visibleTo($user));
    }
}
