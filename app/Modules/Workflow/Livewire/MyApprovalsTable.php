<?php

namespace App\Modules\Workflow\Livewire;

use App\Core\Livewire\Table\Column;
use App\Core\Livewire\Table\DataTable;
use App\Core\Livewire\Table\SelectFilter;
use App\Models\User;
use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Models\Approval;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\HtmlString;
use Livewire\Attributes\On;

/**
 * Workflow → My Approvals: what the logged-in user must answer (default) or has answered. [WF-06]
 * No permission needed: everyone sees only their own rows.
 */
class MyApprovalsTable extends DataTable
{
    /** @var array<int, string> users.id => name, so each name is looked up once per render */
    private array $userNames = [];

    /** @return Builder<Approval> */
    protected function query(): Builder
    {
        $me = (int) auth()->id();

        $query = Approval::query()->with(['approvable', 'step.workflow.approvalType']);

        return ($this->filters['show'] ?? 'waiting') === 'answered'
            ? $query->where('responded_by', $me)
            : $query->where('approver_user_id', $me)->where('status', ApprovalStatus::Pending);
    }

    protected function filters(): array
    {
        return [
            // Handled in query() (the "waiting" default must apply even with no filter chosen).
            SelectFilter::make('show', __('Show'), ['waiting' => __('Waiting for me'), 'answered' => __('Answered by me')])
                ->query(fn () => null),
        ];
    }

    protected function defaultSort(): array
    {
        return ['created_at' => 'desc'];
    }

    protected function columns(): array
    {
        return [
            Column::make(__('Type'), 'type')->alwaysVisible()
                ->format(fn (Approval $a) => $a->step->workflow->approvalType->name),
            Column::make(__('Subject'), 'subject')->alwaysVisible()
                ->format(fn (Approval $a) => new HtmlString(Blade::render(
                    '<button type="button" x-on:click="$dispatch(\'open-approval\', { id: {{ $id }} })" class="text-left font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $subject }}</button>',
                    ['id' => $a->id, 'subject' => $this->subject($a)],
                ))),
            Column::make(__('Requested by'), 'requester_user_id')
                ->format(fn (Approval $a) => $this->userName($a->requester_user_id)),
            Column::make(__('Level'), 'level')->sortable(),
            Column::make(__('Asked'), 'created_at')->sortable()
                ->format(fn (Approval $a) => format_datetime($a->created_at)),
            Column::make(__('Status'), 'status')
                ->format(fn (Approval $a) => new HtmlString(Blade::render(
                    '<x-ui.badge :color="$color">{{ $label }}</x-ui.badge>',
                    ['color' => $a->status->color(), 'label' => $a->status->label()],
                ))),
        ];
    }

    private function subject(Approval $approval): string
    {
        return $approval->approvable instanceof Approvable ? $approval->approvable->approvalSubject() : __('(deleted)');
    }

    private function userName(?int $id): string
    {
        if ($id === null) {
            return '-';
        }

        return $this->userNames[$id] ??= User::find($id)->name ?? __('Deleted user');
    }

    #[On('approval-answered')]
    public function refreshList(): void
    {
        // Re-renders the list.
    }
}
