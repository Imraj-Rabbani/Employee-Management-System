<?php

namespace App\Modules\Payroll\Livewire\Attendance;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Modules\Payroll\Models\Device;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;

/** List of attendance devices with their last sync. Click a name to edit it or "Sync now". [TA-04] */
class DeviceTable extends DataTable
{
    /** @var array<string, string> */
    protected $listeners = ['device-saved' => '$refresh'];

    /** @return Builder<Device> */
    protected function query(): Builder
    {
        return Device::query()->with('orgUnit');
    }

    protected function defaultSort(): array
    {
        return ['name' => 'asc'];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Name'), 'name')->sortable()->searchable()->alwaysVisible()->opens('edit-device'),
            Column::make(__('Address'), 'ip')->searchable()->format(fn (Device $d) => $d->ip.':'.$d->port),
            Column::make(__('Org unit'), 'org_unit_id')->format(fn (Device $d) => $d->orgUnit->name ?? '—'),
            Column::make(__('Last sync'), 'last_synced_at')->sortable()->format(fn (Device $d) => new HtmlString(Blade::render(
                '@if ($d->last_sync_error)<x-ui.badge color="danger" :title="$d->last_sync_error">{{ __(\'Failed\') }}</x-ui.badge> <span class="text-xs text-red-700 dark:text-red-300">{{ $d->last_sync_error }}</span>'
                .'@elseif ($d->last_synced_at){{ format_datetime($d->last_synced_at) }}'
                .'@else<span class="text-zinc-400">{{ __(\'Never\') }}</span>@endif',
                ['d' => $d],
            ))),
            Column::active(),
        ];
    }
}
