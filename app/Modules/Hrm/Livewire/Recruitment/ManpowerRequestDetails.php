<?php

namespace App\Modules\Hrm\Livewire\Recruitment;

use App\Modules\Hrm\Models\ManpowerRequest;
use App\Modules\Workflow\Exceptions\WorkflowException;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;

/**
 * The body of one manpower request's page: status, details, approval timeline, and the buttons
 * Edit / Send for approval. [REC-01]
 *
 * A Livewire component (not plain Blade) so it can redraw itself after "Send" or after the edit modal
 * saved, without reloading the page.
 */
class ManpowerRequestDetails extends Component
{
    /** Locked: the browser cannot swap in someone else's request id. */
    #[Locked]
    public int $requestId;

    /**
     * Sends a draft (or a request sent back for changes) into the "manpower_request" workflow.
     * The engine picks the workflow and the approvers; it refuses when there is no workflow, or a
     * level has nobody to approve. Those messages are written for users, so we show them as they are.
     */
    public function submit(): void
    {
        $request = $this->request();

        if (! $request->isEditable()) {
            $this->dispatch('toast', message: __('This request is already waiting for approval or answered.'), variant: 'danger');

            return;
        }

        try {
            $request->submitForApproval(Auth::user());
        } catch (WorkflowException $e) {
            $this->dispatch('toast', message: $e->getMessage(), variant: 'danger');

            return;
        }

        $this->dispatch('toast', message: __('Request :no sent for approval.', ['no' => $request->number()]), variant: 'success');
    }

    /** The edit modal saved: just draw again with the new values. */
    #[On('manpower-request-saved')]
    public function refreshDetails(): void {}

    /** Through visibleTo(): a request outside the user's org units gives 404, even here. */
    private function request(): ManpowerRequest
    {
        return ManpowerRequest::query()->visibleTo(Auth::user())
            ->with(['orgUnit', 'designation', 'position', 'grade', 'employmentType', 'replacesEmployee', 'approvals'])
            ->findOrFail($this->requestId);
    }

    public function render(): View
    {
        $request = $this->request();

        return view('hrm::livewire.recruitment.manpower-request-details', [
            'request' => $request,
            'status' => $request->approvalStatus(),
        ]);
    }
}
