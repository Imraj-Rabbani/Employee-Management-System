<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Modules\Hrm\Scopes\VisibleToCurrentUser;
use App\Modules\Payroll\Models\Shift;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/** List of shifts. Click a row's name to edit it. [TA-01] */
class ShiftTable extends DataTable
{
    /** @var array<string, string> refresh the list when its form saved something */
    protected $listeners = ['shift-saved' => '$refresh'];

    /** @return Builder<Shift> */
    protected function query(): Builder
    {
        // Employees counted company-wide (not only those this user may see): it says whether the shift is in use.
        return Shift::query()->with('orgUnit')
            ->withCount(['employees' => fn (Builder $q) => $q->withoutGlobalScope(VisibleToCurrentUser::class)]);
    }

    protected function defaultSort(): array
    {
        return ['name' => 'asc'];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Name'), 'name')->sortable()->searchable()->alwaysVisible()->opens('edit-shift'),
            Column::make(__('Code'), 'code')->sortable()->searchable(),
            Column::make(__('Time'), 'start_time')->sortable()->format(fn (Shift $s) => $s->timesLabel()),
            Column::make(__('Hours'), 'hours')->sortable()->alignRight(),
            Column::make(__('Grace'), 'grace_minutes')->sortable()->alignRight()
                ->format(fn (Shift $s) => $s->grace_minutes ? __(':n min', ['n' => $s->grace_minutes]) : '—'),
            Column::make(__('For'), 'org_unit_id')->format(fn (Shift $s) => $s->orgUnit->name ?? __('Whole company')),
            Column::make(__('Employees'), 'employees_count')->sortable()->alignRight(),
            Column::make(__('Default'), 'is_default')->sortable()->format(fn (Model $row) => $row->getAttribute('is_default')
                ? new HtmlString(Blade::render('<x-ui.badge color="info">{{ __(\'Default\') }}</x-ui.badge>'))
                : ''),
            Column::active(),
        ];
    }
}
