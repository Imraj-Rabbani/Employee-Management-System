<?php

namespace App\Modules\Workflow\Livewire;

use App\Core\Support\TenantUploads;
use App\Models\User;
use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Exceptions\WorkflowException;
use App\Modules\Workflow\Models\Approval;
use App\Modules\Workflow\Services\WorkflowEngine;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;

/**
 * Modal "approval-respond": one approval with its timeline, and (if it is still waiting for
 * the logged-in user) Approve / Send back / Reject. Opened by the "open-approval" browser event.
 */
class RespondForm extends Component
{
    use WithFileUploads;

    #[Locked]
    public ?int $approvalId = null;

    public string $comment = '';

    /** @var TemporaryUploadedFile|null */
    public $attachment = null;

    #[On('open-approval')]
    public function open(int $id): void
    {
        $this->reset('comment', 'attachment');
        $this->resetValidation();
        $this->approvalId = $this->find($id)->id;

        Flux::modal('approval-respond')->show();
    }

    public function approve(): void
    {
        $this->answer(ApprovalStatus::Approved);
    }

    public function sendBack(): void
    {
        $this->answer(ApprovalStatus::Review);
    }

    public function reject(): void
    {
        $this->answer(ApprovalStatus::Rejected);
    }

    private function answer(ApprovalStatus $decision): void
    {
        $this->validate([
            'comment' => ['nullable', 'string', 'max:1000'],
            'attachment' => ['nullable', 'file', 'max:5120', 'mimes:pdf,jpg,jpeg,png,doc,docx,xls,xlsx'],
        ]);

        $approval = $this->find((int) $this->approvalId);
        /** @var User $me */
        $me = auth()->user();

        $uploads = app(TenantUploads::class);
        $path = $this->attachment ? $uploads->store($this->attachment, 'approvals') : null;

        try {
            app(WorkflowEngine::class)->respond($approval, $decision, $me, $this->comment, $path);
        } catch (WorkflowException $e) {
            $uploads->delete($path);   // the answer was not saved, so neither is its file
            $this->addError('comment', $e->getMessage());

            return;
        }

        Flux::modal('approval-respond')->close();
        $this->dispatch('approval-answered');
        $this->dispatch('toast', message: __('Saved: :status.', ['status' => $decision->label()]), variant: 'success');
    }

    /** Only rows the user must answer or has answered; anything else is "not found". */
    private function find(int $id): Approval
    {
        $me = (int) auth()->id();

        return Approval::with(['approvable', 'step.workflow.approvalType'])
            ->where(fn ($q) => $q->where('approver_user_id', $me)->orWhere('responded_by', $me))
            ->findOrFail($id);
    }

    public function render(): View
    {
        $approval = $this->approvalId ? $this->find($this->approvalId) : null;
        $document = $approval?->approvable;

        return view('workflow::livewire.respond-form', [
            'approval' => $approval,
            'document' => $document instanceof Approvable ? $document : null,
            'requester' => $approval ? User::find($approval->requester_user_id) : null,
            'canAnswer' => $approval?->status === ApprovalStatus::Pending && $approval->approver_user_id === auth()->id(),
        ]);
    }
}
