<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Core\Livewire\Table\SelectFilter;
use App\Modules\Payroll\Models\Holiday;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/** List of holidays, newest first, with a Year filter. Click a title to edit it. [TA-02] */
class HolidayTable extends DataTable
{
    /** @var array<string, string> */
    protected $listeners = ['holiday-saved' => '$refresh'];

    /** @return Builder<Holiday> */
    protected function query(): Builder
    {
        return Holiday::query()->with('profiles:id,name');
    }

    protected function defaultSort(): array
    {
        return ['date' => 'desc'];
    }

    protected function filters(): array
    {
        // Years that have holidays, plus this year and next (so planning next year starts with an empty list).
        $years = Holiday::query()->selectRaw('YEAR(date) as y')->distinct()->pluck('y')
            ->push(now()->year, now()->year + 1)->unique()->sortDesc()
            ->mapWithKeys(fn ($y) => [(string) $y => (string) $y])->all();

        return [
            SelectFilter::make('year', __('Year'), $years)
                ->query(fn (Builder $q, string $year) => $q->whereIn('id', Holiday::query()->inYear((int) $year)->select('id'))),
            SelectFilter::make('type', __('Type'), ['public' => __('Public'), 'festival' => __('Festival')])
                ->query(fn (Builder $q, string $type) => $q->where($type === 'public' ? 'is_public' : 'is_festival', true)),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Date'), 'date')->sortable()->format(fn (Holiday $h) => $h->repeats_yearly
                ? $h->date->translatedFormat('j F').' · '.__('every year')
                : format_date($h->date).' · '.$h->date->translatedFormat('l')),
            Column::make(__('Title'), 'title')->sortable()->searchable()->alwaysVisible()->opens('edit-holiday'),
            Column::make(__('Type'), 'is_public')->format(fn (Holiday $h) => new HtmlString(Blade::render(
                '@if ($h->is_public)<x-ui.badge color="info">{{ __(\'Public\') }}</x-ui.badge>@endif '
                .'@if ($h->is_festival)<x-ui.badge color="warning">{{ __(\'Festival\') }}</x-ui.badge>@endif',
                ['h' => $h],
            ))),
            Column::make(__('Calendars'), 'id')->format(fn (Holiday $h) => $h->profiles->pluck('name')->join(', ') ?: '—'),
        ];
    }
}
