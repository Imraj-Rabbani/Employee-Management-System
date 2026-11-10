<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Models\User;
use App\Modules\Hrm\Database\Factories\JobPostingFactory;
use App\Modules\Hrm\Enums\JobPostingStatus;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A job advertisement, made from an approved manpower request. [REC-02]
 *
 *     $posting->manpowerRequest              the approved request it hires for
 *     $posting->isAcceptingApplications()    Open AND today between open_date and close_date
 *     JobPosting::visibleTo($user)->get()    postings of the org units the user can see (via the request)
 *
 * @property int $id
 * @property int $manpower_request_id
 * @property string $title
 * @property string|null $description
 * @property string|null $requirements
 * @property int $openings
 * @property Carbon $open_date
 * @property Carbon|null $close_date
 * @property JobPostingStatus $status
 * @property int|null $created_by_user_id
 * @property Carbon $created_at
 */
#[UseFactory(JobPostingFactory::class)]
class JobPosting extends TenantModel
{
    /** @use HasFactory<JobPostingFactory> */
    use HasFactory;

    protected $fillable = [
        'manpower_request_id', 'title', 'description', 'requirements', 'openings', 'open_date', 'close_date',
        'status', 'created_by_user_id',
    ];

    protected $attributes = ['status' => 'draft'];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'openings' => 'integer',
            'open_date' => 'date',
            'close_date' => 'date',
            'status' => JobPostingStatus::class,
        ];
    }

    /** @return BelongsTo<ManpowerRequest, $this> */
    public function manpowerRequest(): BelongsTo
    {
        return $this->belongsTo(ManpowerRequest::class);
    }

    /** @return HasMany<Application, $this> */
    public function applications(): HasMany
    {
        return $this->hasMany(Application::class);
    }

    /**
     * The same org-unit limit as manpower requests: whereHas() keeps only postings whose request
     * passes ManpowerRequest::visibleTo().
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        $query->whereHas('manpowerRequest', fn (Builder $q) => $q->visibleTo($user));
    }

    /** "JOB-0007" */
    public function number(): string
    {
        return 'JOB-'.str_pad((string) $this->id, 4, '0', STR_PAD_LEFT);
    }

    /** Open, and today is inside the dates. Applications (6.4) may only be added then. */
    public function isAcceptingApplications(?Carbon $today = null): bool
    {
        $today ??= today();

        return $this->status === JobPostingStatus::Open
            && $this->open_date->lte($today)
            && ($this->close_date === null || $this->close_date->gte($today));
    }

    /**
     * Why this posting cannot be opened (or reopened) now, or null when it can. Used by the page's
     * Open / Reopen button; the text is shown to the user as it is.
     */
    public function openProblem(): ?string
    {
        if ($this->status === JobPostingStatus::Open) {
            return __('This posting is already open.');
        }

        if ($this->close_date !== null && $this->close_date->lt(today())) {
            return __('The close date has passed. Change it first (Edit), then open the posting.');
        }

        // A closed posting did not count against the request; reopening it must fit what is left.
        if ($this->status === JobPostingStatus::Closed && $this->openings > $this->manpowerRequest->openingsToAdvertise($this->id)) {
            return __('The request has only :max opening(s) left to advertise. Lower the openings first (Edit).', [
                'max' => $this->manpowerRequest->openingsToAdvertise($this->id),
            ]);
        }

        return null;
    }

    /** Draft / Closed → Open. Call openProblem() first. */
    public function open(): void
    {
        if (($problem = $this->openProblem()) !== null) {
            throw new \LogicException($problem);
        }

        $this->update(['status' => JobPostingStatus::Open]);
    }

    /** Open → Closed: no new applications. Existing ones carry on through the pipeline (6.5). */
    public function close(): void
    {
        if ($this->status !== JobPostingStatus::Open) {
            throw new \LogicException(__('Only an open posting can be closed.'));
        }

        $this->update(['status' => JobPostingStatus::Closed]);
    }

    /** One line for the page: "Taking applications", "Opens on 1 Dec", "Close date passed" ... */
    public function applicationWindow(): string
    {
        return match (true) {
            $this->status !== JobPostingStatus::Open => $this->status->label(),
            $this->open_date->gt(today()) => __('Opens on :date', ['date' => format_date($this->open_date)]),
            $this->isAcceptingApplications() => __('Taking applications'),
            default => __('Close date passed: close the posting'),
        };
    }
}
