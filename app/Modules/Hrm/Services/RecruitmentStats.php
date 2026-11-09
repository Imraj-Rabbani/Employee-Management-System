<?php

namespace App\Modules\Hrm\Services;

use App\Models\User;
use App\Modules\Hrm\Enums\ApplicationStage;
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\ApplicationStageChange;
use App\Modules\Hrm\Models\JobPosting;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * The numbers behind the recruitment reports (6.9), worked out from the stage HISTORY, not only from
 * where applications are now. [REC-04, REC-07]
 *
 *   funnel()   per posting: how many applications EVER reached each step. Someone rejected after the
 *              interview still counts as "reached interview". Skipped stages count as reached
 *              (applied → interview also counts for shortlisted), so every step ≤ the one before.
 *   hires()    every hire (the history row "→ hired"): days from applying to being hired ("time to hire")
 *              and from the manpower request's approval to the hire ("time to fill").
 *   summary()  count, average and median of those days.
 */
/**
 * @phpstan-type FunnelRow array{posting: JobPosting, reached: non-empty-array<int, int>, rejected: int, withdrawn: int}
 * @phpstan-type Hire array{application: Application, hired_at: Carbon, days_to_hire: int, days_to_fill: int|null}
 */
class RecruitmentStats
{
    /** The steps of the funnel, in order (ApplicationStage::step() 1 … 5). */
    public const STEPS = [ApplicationStage::Applied, ApplicationStage::Shortlisted, ApplicationStage::Interview, ApplicationStage::Offered, ApplicationStage::Hired];

    /**
     * @param  Collection<int, JobPosting>  $postings
     * @return Collection<int, FunnelRow>
     */
    public function funnel(Collection $postings): Collection
    {
        $ids = $postings->pluck('id')->all();

        // Highest step each application ever reached: one query over the history.
        $changes = ApplicationStageChange::query()
            ->join('applications', 'applications.id', '=', 'application_stage_changes.application_id')
            ->whereIn('applications.job_posting_id', $ids)
            ->get(['application_stage_changes.application_id', 'application_stage_changes.to_stage', 'applications.job_posting_id']);

        $highest = [];   // application id => [posting id, highest step]
        foreach ($changes as $change) {
            $step = (int) $change->to_stage->step();
            $id = $change->application_id;
            $highest[$id] = [(int) $change->getAttribute('job_posting_id'), max($step, $highest[$id][1] ?? 0)];
        }

        $closed = Application::query()->whereIn('job_posting_id', $ids)
            ->whereIn('stage', [ApplicationStage::Rejected, ApplicationStage::Withdrawn])
            ->get(['job_posting_id', 'stage']);

        return $postings->map(function (JobPosting $posting) use ($highest, $closed) {
            $reached = array_fill(1, count(self::STEPS), 0);
            foreach ($highest as [$postingId, $step]) {
                if ($postingId === $posting->id) {
                    for ($s = 1; $s <= $step; $s++) {
                        $reached[$s]++;
                    }
                }
            }
            $mine = $closed->where('job_posting_id', $posting->id);

            return [
                'posting' => $posting,
                'reached' => $reached,
                'rejected' => $mine->where('stage', ApplicationStage::Rejected)->count(),
                'withdrawn' => $mine->where('stage', ApplicationStage::Withdrawn)->count(),
            ];
        })->values();
    }

    /** "45%" of $from, or "—" when there is nothing to divide by. */
    public static function percent(int $part, int $from): string
    {
        return $from === 0 ? '—' : round($part / $from * 100).'%';
    }

    /**
     * Every hire the user may see, newest first. $since = only hires on or after that date.
     *
     * @return Collection<int, Hire>
     */
    public function hires(User $user, ?Carbon $since = null): Collection
    {
        return ApplicationStageChange::query()
            ->where('to_stage', ApplicationStage::Hired)
            ->when($since, fn ($q) => $q->where('created_at', '>=', $since->copy()->startOfDay()))
            ->whereHas('application', fn ($q) => $q->visibleTo($user))
            ->with(['application.applicant', 'application.hiredEmployee', 'application.jobPosting.manpowerRequest.orgUnit'])
            ->orderByDesc('created_at')
            ->get()
            ->map(function (ApplicationStageChange $change) {
                // whereHas() above guarantees it; fail loudly if that ever stops being true.
                $application = $change->application ?? throw new \LogicException('Hire history row without an application.');
                $approved = $application->jobPosting->manpowerRequest->approved_at;

                return [
                    'application' => $application,
                    'hired_at' => $change->created_at,
                    'days_to_hire' => (int) $application->applied_on->startOfDay()->diffInDays($change->created_at->copy()->startOfDay()),
                    'days_to_fill' => $approved ? (int) $approved->copy()->startOfDay()->diffInDays($change->created_at->copy()->startOfDay()) : null,
                ];
            });
    }

    /**
     * @param  Collection<int, int|null>  $days
     * @return array{count: int, average: float|null, median: float|null}
     */
    public function summary(Collection $days): array
    {
        $days = $days->filter(fn ($d) => $d !== null)->sort()->values();

        return [
            'count' => $days->count(),
            'average' => $days->isEmpty() ? null : round((float) $days->avg(), 1),
            'median' => $days->isEmpty() ? null : (float) $days->median(),
        ];
    }
}
