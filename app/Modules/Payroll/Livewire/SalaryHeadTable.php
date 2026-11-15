<?php

namespace App\Modules\Payroll\Livewire;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Modules\Payroll\Models\SalaryHead;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/** List of salary heads. Click a head's name to edit it. [PC-01] */
class SalaryHeadTable extends DataTable
{
    /** @var array<string, string> refresh the list when its form saved something */
    protected $listeners = ['salary-head-saved' => '$refresh'];

    /** @return Builder<SalaryHead> */
    protected function query(): Builder
    {
        return SalaryHead::query();
    }

    protected function defaultSort(): array
    {
        return ['sequence' => 'asc', 'name' => 'asc'];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Order'), 'sequence')->sortable(),
            Column::make(__('Code'), 'code')->sortable()->searchable(),
            Column::make(__('Name'), 'name')->sortable()->searchable()->alwaysVisible()->opens('edit-salary-head'),
            Column::make(__('Type'), 'type')->sortable()
                ->format(fn (SalaryHead $h) => new HtmlString(Blade::render(
                    '<x-ui.badge :color="$color">{{ $label }}</x-ui.badge>',
                    ['color' => $h->type->color(), 'label' => $h->type->label()],
                ))),
            Column::make(__('Rule'), 'calc_method')->format(fn (SalaryHead $h) => $h->rule()),
            Column::make(__('In gross'), 'in_gross')->sortable()->format(fn (SalaryHead $h) => $h->in_gross ? __('Yes') : __('No')),
            Column::make(__('Paid'), 'pay_cycle')->format(fn (SalaryHead $h) => $h->pay_cycle->label()),
            Column::active(),
        ];
    }
}
