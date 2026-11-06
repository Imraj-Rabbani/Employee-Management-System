<?php

namespace App\Modules\Workflow\Livewire;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Core\Livewire\Table\SelectFilter;
use App\Modules\Workflow\Models\ApprovalType;
use App\Modules\Workflow\Models\Workflow;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

/** Workflow → Workflow Setup → Workflows tab. [WF-01] */
class WorkflowsTable extends DataTable
{
    /** @return Builder<Workflow> */
    protected function query(): Builder
    {
        return Workflow::query()->with(['approvalType', 'scopeOrgUnit', 'scopeEmploymentType'])->withCount('steps');
    }

    protected function defaultSort(): array
    {
        return ['approval_type_id' => 'asc', 'name' => 'asc'];
    }

    protected function filters(): array
    {
        return [
            SelectFilter::make('approval_type_id', __('Type'), ApprovalType::orderBy('name')->pluck('name', 'id')->mapWithKeys(fn ($name, $id) => [(string) $id => $name])->all()),
        ];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Workflow'), 'name')->sortable()->searchable()->alwaysVisible()
                ->format(fn (Workflow $w) => new HtmlString(Blade::render(
                    '<button type="button" x-on:click="$dispatch(\'edit-workflow\', { id: {{ $id }} })" class="font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $name }}</button>',
                    ['id' => $w->id, 'name' => $w->name],
                ))),
            Column::make(__('Type'), 'approval_type_id')
                ->format(fn (Workflow $w) => $w->approvalType->name),
            Column::make(__('Applies to'), 'min_amount')
                ->format(fn (Workflow $w) => collect([
                    $w->scopeOrgUnit?->name,
                    $w->scopeEmploymentType?->name,
                    $w->min_amount !== null ? __('from :amount', ['amount' => number_format((float) $w->min_amount, 2)]) : null,
                ])->filter()->implode(' · ') ?: __('Everything')),
            Column::make(__('Levels'), 'steps_count')->sortable(),
            Column::make(__('Email'), 'send_email')
                ->format(fn (Workflow $w) => $w->send_email ? __('Yes') : __('No')),
            Column::make(__('Active'), 'is_active')->sortable()
                ->format(fn (Workflow $w) => new HtmlString(Blade::render(
                    '<x-ui.badge :color="$on ? \'success\' : \'neutral\'">{{ $on ? __(\'Active\') : __(\'Inactive\') }}</x-ui.badge>',
                    ['on' => $w->is_active],
                ))),
        ];
    }

    #[On('workflow-saved')]
    public function refreshList(): void
    {
        // Re-renders the list.
    }
}
