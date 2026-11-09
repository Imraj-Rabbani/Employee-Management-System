<?php

namespace App\Modules\Hrm\Livewire\Recruitment\Reports;

use App\Core\Exports\ArrayExport;
use App\Modules\Hrm\Enums\JobPostingStatus;
use App\Modules\Hrm\Models\JobPosting;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Services\EmployeeVisibility;
use App\Modules\Hrm\Services\RecruitmentStats;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @phpstan-import-type FunnelRow from RecruitmentStats
 *
 * Recruitment report: the pipeline funnel per job posting (6.9). How many applications EVER reached each
 * step, the % kept from "applied", and how many were rejected / withdrew. Numbers: RecruitmentStats::funnel().
 * Only postings of the user's org units. Not a DataTable (rows are worked out in PHP), so it has its own
 * filters and export (ArrayExport).
 */
class PipelineFunnelReport extends Component
{
    #[Url(as: 'status')]
    public string $status = '';

    #[Url(as: 'unit')]
    public string $orgUnit = '';

    /** @return Collection<int, FunnelRow> */
    private function rows(RecruitmentStats $stats): Collection
    {
        $postings = JobPosting::query()->visibleTo(Auth::user())
            ->when(JobPostingStatus::tryFrom($this->status), fn ($q, $status) => $q->where('status', $status))
            ->when($this->orgUnit !== '', fn ($q) => $q->whereHas('manpowerRequest', fn ($r) => $r->whereIn(
                'org_unit_id', [(int) $this->orgUnit, ...(OrgUnit::find((int) $this->orgUnit)?->descendantIds() ?? [])],
            )))
            ->with('manpowerRequest.orgUnit')
            ->orderByDesc('id')
            ->get();

        return $stats->funnel($postings);
    }

    /**
     * @param  Collection<int, FunnelRow>  $rows
     * @return array{reached: array<int, int>, rejected: int, withdrawn: int}
     */
    private function totals(Collection $rows): array
    {
        $reached = array_fill(1, count(RecruitmentStats::STEPS), 0);
        foreach ($rows as $row) {
            foreach ($row['reached'] as $step => $count) {
                $reached[$step] += $count;
            }
        }

        return ['reached' => $reached, 'rejected' => $rows->sum('rejected'), 'withdrawn' => $rows->sum('withdrawn')];
    }

    public function export(RecruitmentStats $stats): BinaryFileResponse
    {
        $headings = [__('Posting'), __('Title'), __('Org unit'), __('Status')];
        foreach (RecruitmentStats::STEPS as $stage) {
            $headings[] = $stage->label();
        }
        $headings = [...$headings, __('Hired %'), __('Rejected'), __('Withdrawn')];

        $data = $this->rows($stats)->map(fn (array $row) => [
            $row['posting']->number(), $row['posting']->title, $row['posting']->manpowerRequest->orgUnit->name ?? '', $row['posting']->status->label(),
            ...array_values($row['reached']),
            RecruitmentStats::percent($row['reached'][5], $row['reached'][1]), $row['rejected'], $row['withdrawn'],
        ])->all();

        return Excel::download(new ArrayExport($headings, $data), 'recruitment-funnel-'.now()->format('Y-m-d').'.xlsx');
    }

    public function render(RecruitmentStats $stats): View
    {
        $rows = $this->rows($stats);

        return view('hrm::livewire.recruitment.reports.pipeline-funnel-report', [
            'rows' => $rows,
            'totals' => $this->totals($rows),
            'steps' => RecruitmentStats::STEPS,
            'statuses' => JobPostingStatus::options(),
            'units' => app(EmployeeVisibility::class)->orgUnitOptionsFor(Auth::user()),
        ]);
    }
}
