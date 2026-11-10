<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Models\User;
use App\Modules\Hrm\Database\Factories\ApplicationFactory;
use App\Modules\Hrm\Enums\ApplicationStage;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One applicant applying to one job posting. [REC-03]
 * Created ONLY through Hrm\Actions\ApplyToPosting (it checks the posting takes applications and the
 * person has not applied to it already). Its stage changes ONLY through Hrm\Actions\MoveApplication,
 * which also writes the history (stageChanges). stage / rejection_reason_id / stage_changed_at are
 * therefore not fillable from forms.
 *
 * @property int $id
 * @property int $applicant_id
 * @property int $job_posting_id
 * @property ApplicationStage $stage
 * @property int|null $rejection_reason_id the current reason (rejected / withdrawn), copied from the last move
 * @property Carbon|null $stage_changed_at when it reached its current stage
 * @property int|null $hired_employee_id the employee made from it by the hire (6.8)
 * @property Carbon $applied_on
 * @property int|null $created_by_user_id
 * @property Carbon $created_at
 */
#[UseFactory(ApplicationFactory::class)]
class Application extends TenantModel
{
    /** @use HasFactory<ApplicationFactory> */
    use HasFactory;

    protected $fillable = ['applicant_id', 'job_posting_id', 'applied_on', 'created_by_user_id'];

    protected $attributes = ['stage' => 'applied'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['stage' => ApplicationStage::class, 'applied_on' => 'date', 'stage_changed_at' => 'datetime'];
    }

    /** @return BelongsTo<Applicant, $this> */
    public function applicant(): BelongsTo
    {
        return $this->belongsTo(Applicant::class);
    }

    /** @return BelongsTo<RejectionReason, $this> */
    public function rejectionReason(): BelongsTo
    {
        return $this->belongsTo(RejectionReason::class);
    }

    /**
     * The history, oldest first. [REC-04]
     *
     * @return HasMany<ApplicationStageChange, $this>
     */
    public function stageChanges(): HasMany
    {
        return $this->hasMany(ApplicationStageChange::class)->orderBy('id');
    }

    /**
     * Interview rounds, round 1 first. [REC-05]
     *
     * @return HasMany<Interview, $this>
     */
    public function interviews(): HasMany
    {
        return $this->hasMany(Interview::class)->orderBy('round');
    }

    /**
     * Job offers, newest first (a new one only after the previous was declined / not approved). [REC-06]
     *
     * @return HasMany<JobOffer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(JobOffer::class)->latest('id');
    }

    /**
     * The employee this application became (6.8). withoutGlobalScope: HR may see the hiring history even
     * when the new employee sits in a unit outside their access.
     *
     * @return BelongsTo<Employee, $this>
     */
    public function hiredEmployee(): BelongsTo
    {
        return $this->belongsTo(Employee::class, 'hired_employee_id')->withoutGlobalScope(VisibleToCurrentUser::class);
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
