<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Modules\Payroll\Models\Roster;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;

/**
 * Payroll → Attendance → Rosters: the list, newest first. [TA-03]
 * Only rosters of the org units the user can see (Roster::visibleTo). Click the number to open the roster.
 */
class RosterTable extends DataTable
{
    /** @var array<string, string> */
    protected $listeners = ['roster-saved' => '$refresh'];

    /** @return Builder<Roster> */
    protected function query(): Builder
    {
        // 'approvals' eager loaded: the Status column calls approvalStatus() on every row.
        return Roster::query()
            ->visibleTo(Auth::user())
            ->with(['orgUnit', 'approvals'])
            ->withCount(['items as employees_count' => fn (Builder $q) => $q->select(DB::raw('count(distinct employee_id)'))]);
    }

    protected function defaultSort(): array
    {
        return ['from_date' => 'desc'];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('No.'), 'id')->sortable()->alwaysVisible()
                ->format(fn (Roster $r) => new HtmlString(Blade::render(
                    '<a href="{{ route(\'payroll.attendance.rosters.show\', $r->id) }}" wire:navigate class="font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $r->number() }}</a>',
                    ['r' => $r],
                ))),
            Column::make(__('Title'), 'title')->sortable()->searchable()->alwaysVisible(),
            Column::make(__('Org unit'), 'org_unit_id')->format(fn (Roster $r) => $r->orgUnit->name ?? '-'),
            Column::make(__('From'), 'from_date')->sortable()->format(fn (Roster $r) => format_date($r->from_date)),
            Column::make(__('To'), 'to_date')->sortable()->format(fn (Roster $r) => format_date($r->to_date)),
            Column::make(__('Employees'), 'employees_count')->sortable()->alignRight(),
            Column::make(__('Status'), 'approved_at')->format(fn (Roster $r) => new HtmlString(Blade::render(
                '<x-ui.badge :color="$b[1]">{{ $b[0] }}</x-ui.badge>',
                ['b' => $r->statusBadge()],
            ))),
        ];
    }
}
