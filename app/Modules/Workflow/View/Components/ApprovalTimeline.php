<?php

namespace App\Modules\Workflow\View\Components;

use App\Models\User;
use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Models\Approval;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Illuminate\View\Component;

/**
 * The approval history of one document, oldest first. [WF-06]
 *
 *     <x-approval-timeline :document="$leave" />
 *
 * A class component (not just a Blade file) because it needs a little PHP first: the
 * approvers' names live in the central users table, so they are loaded in one query here.
 */
class ApprovalTimeline extends Component
{
    /** @var Collection<int, Approval> */
    public Collection $approvals;

    /** @var array<int, string> users.id => name */
    public array $names = [];

    public function __construct(public Approvable $document)
    {
        $this->approvals = $document->approvals()->get()
            ->sortBy([['created_at', 'asc'], ['level', 'asc'], ['id', 'asc']])
            ->values();

        $ids = $this->approvals
            ->flatMap(fn (Approval $a) => [$a->approver_user_id, $a->responded_by, $a->requester_user_id])
            ->filter()->unique()->values()->all();

        $this->names = User::whereIn('id', $ids)->pluck('name', 'id')->all();
    }

    public function name(?int $userId): string
    {
        return $userId === null ? '-' : ($this->names[$userId] ?? __('Deleted user'));
    }

    public function render(): View
    {
        return view('workflow::components.approval-timeline');
    }
}
