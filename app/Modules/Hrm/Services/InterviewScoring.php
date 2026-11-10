<?php

namespace App\Modules\Hrm\Services;

use App\Modules\Hrm\Enums\ApplicationStage;
use App\Modules\Hrm\Enums\InterviewStatus;
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\Interview;
use App\Modules\Hrm\Models\InterviewPanelist;
use App\Modules\Hrm\Models\JobPosting;
use Illuminate\Support\Collection;

/**
 * The interview maths, in one place. All results are percentages (0–100, one decimal). [REC-05]
 *
 *   panelist     one interviewer's weighted score:  Σ (score / MAX × weight) / Σ weight × 100
 *                e.g. Communication 4/5 weight 40, Technical 3/5 weight 60 → (0.8×40 + 0.6×60) / 100 = 68 %
 *                All weights 0 → a plain average (every factor counts the same).
 *   interview    the average of the panelists who SUBMITTED; a cancelled round has no result
 *   application  the average of its COMPLETED rounds that have a result
 *   ranking      a posting's applications by that result, best first; the same result = the same rank
 *                (1, 1, 3). Withdrawn / rejected ones and those without a result are left out.
 *
 * Works on loaded relations, so eager load first, e.g. ranking() does it for a whole posting in one go.
 */
class InterviewScoring
{
    /** Highest score per factor. Scores are 1 … MAX. Change here if the company uses another scale. */
    public const MAX = 5;

    public function panelist(InterviewPanelist $panelist): ?float
    {
        if (! $panelist->isSubmitted() || $panelist->scores->isEmpty()) {
            return null;
        }

        $totalWeight = (float) $panelist->scores->sum(fn ($s) => (float) $s->weight);

        $result = $totalWeight > 0
            ? $panelist->scores->sum(fn ($s) => $s->score / self::MAX * (float) $s->weight) / $totalWeight
            : $panelist->scores->avg(fn ($s) => $s->score / self::MAX);

        return round($result * 100, 1);
    }

    public function interview(Interview $interview): ?float
    {
        if ($interview->status === InterviewStatus::Cancelled) {
            return null;
        }

        return $this->average($interview->panelists->map(fn (InterviewPanelist $p) => $this->panelist($p)));
    }

    public function application(Application $application): ?float
    {
        return $this->average(
            $application->interviews
                ->filter(fn (Interview $i) => $i->status === InterviewStatus::Completed)
                ->map(fn (Interview $i) => $this->interview($i)),
        );
    }

    /**
     * @return Collection<int, array{application: Application, score: float, rank: int}> best first
     */
    public function ranking(JobPosting $posting): Collection
    {
        $applications = $posting->applications()
            ->whereNotIn('stage', [ApplicationStage::Rejected, ApplicationStage::Withdrawn])
            ->with(['applicant', 'interviews.panelists.scores'])   // everything the maths needs, in 5 queries
            ->get();

        $scored = $applications
            ->map(fn (Application $a) => ['application' => $a, 'score' => $this->application($a)])
            ->filter(fn (array $row) => $row['score'] !== null)
            ->sortByDesc('score')
            ->values();

        // "Competition" ranking: 90, 90, 85 → 1, 1, 3.
        $ranked = [];
        foreach ($scored as $i => $row) {
            $previous = $ranked[$i - 1] ?? null;
            $ranked[] = [...$row, 'rank' => $previous !== null && $previous['score'] === $row['score'] ? $previous['rank'] : $i + 1];
        }

        return collect($ranked);
    }

    /** @param  Collection<int, float|null>  $values */
    private function average(Collection $values): ?float
    {
        $values = $values->filter(fn ($v) => $v !== null);

        return $values->isEmpty() ? null : round((float) $values->avg(), 1);
    }
}
