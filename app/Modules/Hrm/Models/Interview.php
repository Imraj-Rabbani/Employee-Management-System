<?php

namespace App\Modules\Hrm\Models;

use App\Core\Models\TenantModel;
use App\Modules\Hrm\Database\Factories\InterviewFactory;
use App\Modules\Hrm\Enums\InterviewStatus;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One interview round of one application. [REC-05]
 *
 *     $interview->panelists          the employees interviewing, each with their scores
 *     InterviewScoring::interview()  the round's result in % (average of the panelists who submitted)
 *
 * @property int $id
 * @property int $application_id
 * @property int $round
 * @property Carbon $scheduled_at
 * @property int $duration_minutes
 * @property string|null $location
 * @property InterviewStatus $status
 * @property string|null $notes
 * @property int|null $created_by_user_id
 */
#[UseFactory(InterviewFactory::class)]
class Interview extends TenantModel
{
    /** @use HasFactory<InterviewFactory> */
    use HasFactory;

    protected $fillable = ['application_id', 'round', 'scheduled_at', 'duration_minutes', 'location', 'status', 'notes', 'created_by_user_id'];

    protected $attributes = ['status' => 'scheduled', 'duration_minutes' => 30];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'round' => 'integer',
            'scheduled_at' => 'datetime',
            'duration_minutes' => 'integer',
            'status' => InterviewStatus::class,
        ];
    }

    /** @return BelongsTo<Application, $this> */
    public function application(): BelongsTo
    {
        return $this->belongsTo(Application::class);
    }

    /** @return HasMany<InterviewPanelist, $this> */
    public function panelists(): HasMany
    {
        return $this->hasMany(InterviewPanelist::class)->orderBy('id');
    }

    /** Why it cannot be completed now, or null. Completing = the scores count for the ranking, no more changes. */
    public function completeProblem(): ?string
    {
        return match (true) {
            $this->status !== InterviewStatus::Scheduled => __('Only a planned interview can be completed.'),
            ! $this->panelists()->whereNotNull('submitted_at')->exists() => __('At least one interviewer must send their scores first.'),
            default => null,
        };
    }

    public function complete(): void
    {
        if ($problem = $this->completeProblem()) {
            throw new \LogicException($problem);
        }
        $this->update(['status' => InterviewStatus::Completed]);
    }

    /** Did not happen. The round keeps its number; a new round can then be planned. */
    public function cancel(): void
    {
        if ($this->status !== InterviewStatus::Scheduled) {
            throw new \LogicException(__('Only a planned interview can be cancelled.'));
        }
        $this->update(['status' => InterviewStatus::Cancelled]);
    }

    /** "Round 2" */
    public function title(): string
    {
        return __('Round :round', ['round' => $this->round]);
    }
}
