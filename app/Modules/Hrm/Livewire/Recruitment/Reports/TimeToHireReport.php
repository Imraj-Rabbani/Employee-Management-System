<?php

namespace App\Modules\Hrm\Livewire\Recruitment\Reports;

use App\Core\Exports\ArrayExport;
use App\Modules\Hrm\Services\RecruitmentStats;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Url;
use Livewire\Component;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * @phpstan-import-type Hire from RecruitmentStats
 *
 * Recruitment report: time to hire (6.9). [REC-07]
 *
 *   Time to hire   days from the application (applied_on) to the hire
 *   Time to fill   days from the manpower request's approval to the hire
 *
 * Shown as tiles (count, average, median), per org unit, and hire by hire. The hire date is the day
 * "Hire" was pressed (the history row), not the employee's join date. Numbers: RecruitmentStats::hires().
 */
class TimeToHireReport extends Component
{
    /** Hires in the last N months ('' = all time). */
    #[Url(as: 'months')]
    public string $months = '12';

    /** @return Collection<int, Hire> */
    private function hires(RecruitmentStats $stats): Collection
    {
        $months = in_array($this->months, ['3', '6', '12'], true) ? (int) $this->months : null;

        return $stats->hires(Auth::user(), $months ? today()->subMonths($months) : null);
    }

    public function export(RecruitmentStats $stats): BinaryFileResponse
    {
        $rows = $this->hires($stats)->map(fn (array $h) => [
            $h['application']->applicant->name,
            $h['application']->hiredEmployee->code ?? '',
            $h['application']->jobPosting->number().' · '.$h['application']->jobPosting->title,
            $h['application']->jobPosting->manpowerRequest->orgUnit->name ?? '',
            format_date($h['application']->applied_on),
            format_date($h['hired_at']),
            $h['days_to_hire'],
            $h['days_to_fill'],
        ])->all();

        return Excel::download(new ArrayExport(
            [__('Applicant'), __('Employee'), __('Posting'), __('Org unit'), __('Applied'), __('Hired'), __('Days to hire'), __('Days to fill')],
            $rows,
        ), 'time-to-hire-'.now()->format('Y-m-d').'.xlsx');
    }

    public function render(RecruitmentStats $stats): View
    {
        $hires = $this->hires($stats);

        $byUnit = $hires->groupBy(fn (array $h) => $h['application']->jobPosting->manpowerRequest->orgUnit->name ?? '—')
            ->map(fn (Collection $group, string $unit) => [
                'unit' => $unit,
                'hire' => $stats->summary($group->pluck('days_to_hire')),
                'fill' => $stats->summary($group->pluck('days_to_fill')),
            ])
            ->sortBy('unit')->values();

        return view('hrm::livewire.recruitment.reports.time-to-hire-report', [
            'hires' => $hires,
            'toHire' => $stats->summary($hires->pluck('days_to_hire')),
            'toFill' => $stats->summary($hires->pluck('days_to_fill')),
            'byUnit' => $byUnit,
            'periods' => ['3' => __('Last 3 months'), '6' => __('Last 6 months'), '12' => __('Last 12 months')],
        ]);
    }
}
