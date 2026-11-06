<?php

namespace App\Modules\Workflow\Livewire;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Modules\Workflow\Models\ApprovalType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

/** Workflow → Workflow Setup → Approval types tab. [WF-01] */
class ApprovalTypesTable extends DataTable
{
    /** @return Builder<ApprovalType> */
    protected function query(): Builder
    {
        return ApprovalType::query()->withCount('workflows');
    }

    protected function defaultSort(): array
    {
        return ['name' => 'asc'];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Name'), 'name')->sortable()->searchable()->alwaysVisible()
                ->format(fn (ApprovalType $type) => new HtmlString(Blade::render(
                    '<button type="button" x-on:click="$dispatch(\'edit-approval-type\', { id: {{ $id }} })" class="font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $name }}</button>',
                    ['id' => $type->id, 'name' => $type->name],
                ))),
            Column::make(__('Key'), 'key')->sortable()->searchable(),
            Column::make(__('Levels'), 'is_chained')
                ->format(fn (ApprovalType $type) => $type->is_chained ? __('One after another') : __('All at once')),
            Column::make(__('Workflows'), 'workflows_count')->sortable(),
            Column::make(__('Active'), 'is_active')->sortable()
                ->format(fn (ApprovalType $type) => new HtmlString(Blade::render(
                    '<x-ui.badge :color="$on ? \'success\' : \'neutral\'">{{ $on ? __(\'Active\') : __(\'Inactive\') }}</x-ui.badge>',
                    ['on' => $type->is_active],
                ))),
        ];
    }

    #[On('approval-type-saved')]
    public function refreshList(): void
    {
        // Re-renders the list.
    }
}
