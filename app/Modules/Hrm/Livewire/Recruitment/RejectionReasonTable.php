<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Modules\Hrm\Models\RejectionReason;
use Illuminate\Database\Eloquent\Builder;

/** List of rejection reasons. Click a row's name to edit it. [REC-04] */
class RejectionReasonTable extends DataTable
{
    /** @var array<string, string> refresh the list when its form saved something */
    protected $listeners = ['rejection-reason-saved' => '$refresh'];

    /** @return Builder<RejectionReason> */
    protected function query(): Builder
    {
        return RejectionReason::query();
    }

    protected function defaultSort(): array
    {
        return ['name' => 'asc'];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Name'), 'name')->sortable()->searchable()->alwaysVisible()->opens('edit-rejection-reason'),
            Column::active(),
        ];
    }
}
