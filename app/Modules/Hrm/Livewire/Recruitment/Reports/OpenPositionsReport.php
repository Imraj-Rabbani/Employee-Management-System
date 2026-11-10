<?php

namespace App\Modules\Hrm\Livewire\Recruitment\Reports;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Core\Livewire\Table\SelectFilter;
use App\Modules\Hrm\Enums\JobPostingStatus;
use App\Modules\Hrm\Models\ManpowerRequest;
use App\Modules\Hrm\Models\OrgUnit;
use App\Modules\Hrm\Services\EmployeeVisibility;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * Recruitment report: open positions (6.9). Every APPROVED manpower request that still has people to hire:
 * how many are wanted, hired, still open, and how many of those are advertised right now. [REC-01..07]
 *
 * One query for the whole page: "open_count" is worked out by MySQL (headcount − hired_count) and
 * "advertised" with withSum() (Draft / Open postings), so both can be sorted and exported.
 * Only the org units the user can see (ManpowerRequest::visibleTo). Excel export comes from DataTable.
 */
class OpenPositionsReport extends DataTable
{
    /** @return Builder<ManpowerRequest> */
    protected function query(): Builder
    {
        return ManpowerRequest::query()
            ->approved()
            ->visibleTo(Auth::user())
            ->whereColumn('hired_count', '<', 'headcount')
            ->select('manpower_requests.*', DB::raw('headcount - hired_count as open_count'))
            ->withSum(['jobPostings as advertised' => fn ($q) => $q->whereIn('status', [JobPostingStatus::Draft, JobPostingStatus::Open])], 'openings')
            ->with(['designation', 'orgUnit']);
    }

    protected function defaultSort(): array
    {
        return ['needed_by' => 'asc'];
    }

    protected function filters(): array
    {
        $units = app(EmployeeVisibility::class)->orgUnitOptionsFor(Auth::user());

        return [
            SelectFilter::make('org_unit', __('Org unit'), $this->stringKeys($units))
                ->query(fn (Builder $q, string $id) => $q->whereIn('org_unit_id', [(int) $id, ...(OrgUnit::find((int) $id)?->descendantIds() ?? [])])),
            SelectFilter::make('timing', __('Needed by'), ['overdue' => __('Overdue'), 'month' => __('Within 30 days')])
                ->query(fn (Builder $q, string $v) => $v === 'overdue'
                    ? $q->where('needed_by', '<', today())
                    : $q->whereBetween('needed_by', [today(), today()->addDays(30)])),
            SelectFilter::make('advertised', __('Advertised'), ['no' => __('Not advertised'), 'part' => __('Partly'), 'all' => __('Fully')])
                ->query(fn (Builder $q, string $v) => match ($v) {
                    'no' => $q->having('advertised', '=', 0)->orHavingNull('advertised'),
                    'part' => $q->having('advertised', '>', 0)->havingRaw('advertised < open_count'),
                    default => $q->havingRaw('advertised >= open_count'),
                }),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Request'), 'id')->sortable()->alwaysVisible()
                ->format(fn (ManpowerRequest $r) => new HtmlString(Blade::render(
                    '<a href="{{ route(\'hrm.recruitment.requests.show\', $r->id) }}" wire:navigate class="font-medium text-brand-800 hover:underline dark:text-brand-200">{{ $r->number() }}</a>',
                    ['r' => $r],
                ))),
            Column::make(__('Designation'), 'designation_id')->alwaysVisible()->format(fn (ManpowerRequest $r) => $r->designation->name ?? '-'),
            Column::make(__('Org unit'), 'org_unit_id')->format(fn (ManpowerRequest $r) => $r->orgUnit->name ?? '-'),
            Column::make(__('Type'), 'type')->format(fn (ManpowerRequest $r) => $r->type->label()),
            Column::make(__('Wanted'), 'headcount')->sortable()->alignRight(),
            Column::make(__('Hired'), 'hired_count')->sortable()->alignRight(),
            Column::make(__('Open'), 'open_count')->sortable()->alignRight(),
            Column::make(__('Advertised'), 'advertised')->sortable()->alignRight()->format(fn (ManpowerRequest $r) => (int) $r->getAttribute('advertised')),
            Column::make(__('Needed by'), 'needed_by')->sortable()
                ->format(fn (ManpowerRequest $r) => new HtmlString(Blade::render(
                    '@if ($d) {{ format_date($d) }} @if ($d->isPast()) <x-ui.badge color="danger">{{ __(\'Overdue\') }}</x-ui.badge> @endif @else — @endif',
                    ['d' => $r->needed_by],
                ))),
            Column::make(__('Open for'), 'approved_at')->sortable()
                ->format(fn (ManpowerRequest $r) => $r->approved_at ? trans_choice(':count day|:count days', (int) $r->approved_at->diffInDays(now()), ['count' => (int) $r->approved_at->diffInDays(now())]) : '-'),
        ];
    }
}
