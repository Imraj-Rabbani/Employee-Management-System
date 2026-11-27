<?php

namespace App\Modules\Hrm\Services;

use App\Modules\Hrm\Enums\ApplicationStage;
use App\Modules\Hrm\Enums\InterviewStatus;
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\Interview;
use App\Modules\Hrm\Models\InterviewPanelist;
use App\Modules\Hrm\Models\InterviewStage;
use App\Modules\Hrm\Models\JobPosting;
use Illuminate\Support\Collection;

/**
 * The interview maths, in one place. All results are percentages (0–100, one decimal).
 *
 *   panelist     one interviewer's weighted score:  Σ (score / MAX × weight) / Σ weight × 100
 *                e.g. Communication 4/5 weight 40, Technical 3/5 weight 60 → (0.8×40 + 0.6×60) / 100 = 68 %
 *                All weights 0 → a plain average (every factor counts the same).
 *   interview    the average of the panelists who SUBMITTED; a cancelled round has no result
 *   stages       per stage of the posting: the result of the application's COMPLETED interview in it
 *   total        the SUM of those stage results (e.g. Stage 1 80 % + Stage 2 65 % = 145)
 *   ranking      a posting's applications by that total, best first; the same total = the same rank
 *                (1, 1, 3). Withdrawn / rejected ones and those without any result are left out.
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

    /**
     * The result of each stage this application has a COMPLETED interview in: stage id => %.
     *
     * @return array<int, float>
     */
    public function stageResults(Application $application): array
    {
        $results = [];
        foreach ($application->interviews->where('status', InterviewStatus::Completed)->sortBy('id') as $interview) {
            $result = $this->interview($interview);
            if ($result !== null) {
                $results[$interview->interview_stage_id] = $result;   // one per stage; a later one wins
            }
        }

        return $results;
    }

    /**
     * The posting's ranking table: its stages (the columns) and one row per application with a result.
     *
     * @return array{stages: Collection<int, InterviewStage>, rows: Collection<int, array{application: Application, scores: non-empty-array<int, float>, total: float, rank: int}>}
     */
    public function ranking(JobPosting $posting): array
    {
        $applications = $posting->applications()
            ->whereNotIn('stage', [ApplicationStage::Rejected, ApplicationStage::Withdrawn])
            ->with(['applicant', 'interviews.panelists.scores'])   // everything the maths needs, in 5 queries
            ->get();

        $scored = $applications
            ->map(function (Application $a) {
                $scores = $this->stageResults($a);

                return ['application' => $a, 'scores' => $scores, 'total' => round(array_sum($scores), 1)];
            })
            ->filter(fn (array $row) => $row['scores'] !== [])
            ->sortByDesc('total')
            ->values();

        // "Competition" ranking: 150, 150, 140 → 1, 1, 3.
        $ranked = [];
        foreach ($scored as $i => $row) {
            $previous = $ranked[$i - 1] ?? null;
            $ranked[] = [...$row, 'rank' => $previous !== null && $previous['total'] === $row['total'] ? $previous['rank'] : $i + 1];
        }

        return ['stages' => $posting->interviewStages()->get(), 'rows' => collect($ranked)];
    }

    /** @param  Collection<int, float|null>  $values */
    private function average(Collection $values): ?float
    {
        $values = $values->filter(fn ($v) => $v !== null);

        return $values->isEmpty() ? null : round((float) $values->avg(), 1);
    }
}
