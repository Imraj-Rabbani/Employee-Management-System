<?php

namespace App\Modules\Core\Livewire;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Modules\Core\Models\Currency;
use Illuminate\Database\Eloquent\Builder;

/** List of currencies. Click a row's name to edit it. [ORG-01] */
class CurrencyTable extends DataTable
{
    /** @var array<string, string> refresh the list when its form saved something */
    protected $listeners = ['currency-saved' => '$refresh'];

    /** @return Builder<Currency> */
    protected function query(): Builder
    {
        return Currency::query();
    }

    protected function defaultSort(): array
    {
        return ['code' => 'asc'];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Code'), 'code')->sortable()->searchable()->alwaysVisible()->opens('edit-currency'),
            Column::make(__('Name'), 'name')->sortable()->searchable(),
            Column::make(__('Symbol'), 'symbol'),
            Column::make(__('Exchange rate'), 'exchange_rate')->alignRight(),
            Column::active(),
        ];
    }
}
