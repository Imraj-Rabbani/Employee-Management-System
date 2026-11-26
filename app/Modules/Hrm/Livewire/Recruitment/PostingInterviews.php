<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Modules\Hrm\Enums\InterviewStatus;
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\Interview;
use App\Modules\Hrm\Models\JobPosting;
use App\Modules\Hrm\Services\InterviewScoring;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * A job posting's interviews and ranking (below the pipeline).
 *
 *   Interviews  tabs "All" + one per stage (its name, with a count); each interview: when, where, the panel
 *               (✓ = scores sent; click a name to open / enter that sheet), its result, Change / Complete / Cancel.
 *   Ranking     InterviewScoring::ranking(): one column per stage (the COMPLETED interview's result) and
 *               the Total (the sum). Every column heading sorts the table (rankSort / rankDir).
 *
 * Permission: the posting page (hrm.recruitment.manage, re-checked on every click); the posting through
 * visibleTo(), and an interview always THROUGH the posting.
 */
class PostingInterviews extends Component
{
    #[Locked]
    public int $postingId;

    /** Interviews tab: 'all' or a stage id. */
    public string $stageTab = 'all';

    /** Ranking sort: 'total', 'name', or 'stage-{id}'; and the direction. */
    public string $rankSort = 'total';

    public string $rankDir = 'desc';

    /** A ranking column heading was clicked: sort by it; the same one again flips the direction. */
    public function sortRanking(string $key): void
    {
        if ($key !== 'total' && $key !== 'name' && ! preg_match('/^stage-\d+$/', $key)) {
            return;
        }
        $this->rankDir = $this->rankSort === $key ? ($this->rankDir === 'desc' ? 'asc' : 'desc') : ($key === 'name' ? 'asc' : 'desc');
        $this->rankSort = $key;
    }

    public function complete(int $interviewId): void
    {
        $interview = $this->interview($interviewId);

        if ($problem = $interview->completeProblem()) {
            $this->dispatch('toast', message: $problem, variant: 'danger');

            return;
        }

        $interview->complete();
        $this->dispatch('toast', message: __(':stage completed: its scores now count in the ranking.', ['stage' => $interview->title()]), variant: 'success');
    }

    public function cancel(int $interviewId): void
    {
        $interview = $this->interview($interviewId);

        if ($interview->status !== InterviewStatus::Scheduled) {
            $this->dispatch('toast', message: __('Only a planned interview can be cancelled.'), variant: 'danger');

            return;
        }

        $interview->cancel();
        $this->dispatch('interview-saved');   // the pipeline shows the "Schedule" button again
        $this->dispatch('toast', message: __(':stage cancelled.', ['stage' => $interview->title()]), variant: 'success');
    }

    /** Scheduled / changed elsewhere, or a score sheet was saved: draw again. */
    #[On('interview-saved')]
    public function refreshInterviews(): void {}

    private function posting(): JobPosting
    {
        return JobPosting::query()->visibleTo(Auth::user())->findOrFail($this->postingId);
    }

    private function interview(int $id): Interview
    {
        return Interview::query()
            ->whereHas('application', fn ($q) => $q->where('job_posting_id', $this->posting()->id))
            ->findOrFail($id);
    }

    public function render(InterviewScoring $scoring): View
    {
        $posting = $this->posting();

        $all = Interview::query()
            ->whereHas('application', fn ($q) => $q->where('job_posting_id', $posting->id))
            ->with(['stage', 'application.applicant', 'panelists.employee', 'panelists.scores'])
            ->orderByRaw("status = 'scheduled' desc")   // planned ones first
            ->orderByDesc('scheduled_at')
            ->get();
        $interviews = $this->stageTab === 'all' ? $all : $all->where('interview_stage_id', (int) $this->stageTab)->values();

        $ranking = $scoring->ranking($posting);

        return view('hrm::livewire.recruitment.posting-interviews', [
            'stages' => $ranking['stages'],
            'stageCounts' => $all->countBy('interview_stage_id'),
            'interviewCount' => $all->count(),
            'interviews' => $interviews,
            'results' => $interviews->mapWithKeys(fn (Interview $i) => [$i->id => $scoring->interview($i)]),
            'ranking' => $this->sortedRanking($ranking['rows']),
        ]);
    }

    /**
     * The ranking rows in the chosen order. A missing stage score sorts after every real score.
     *
     * @param  Collection<int, array{application: Application, scores: array<int, float>, total: float, rank: int}>  $rows
     * @return Collection<int, array{application: Application, scores: array<int, float>, total: float, rank: int}>
     */
    private function sortedRanking(Collection $rows): Collection
    {
        $value = match (true) {
            $this->rankSort === 'name' => fn (array $r) => mb_strtolower($r['application']->applicant->name),
            str_starts_with($this->rankSort, 'stage-') => fn (array $r) => $r['scores'][(int) substr($this->rankSort, 6)] ?? null,
            default => fn (array $r) => $r['total'],
        };

        return $rows->sort(function (array $a, array $b) use ($value) {
            [$x, $y] = [$value($a), $value($b)];
            if ($x === null || $y === null) {
                return ($x === null) <=> ($y === null);   // missing last, whatever the direction
            }

            return $this->rankDir === 'asc' ? $x <=> $y : $y <=> $x;
        })->values();
    }
}
