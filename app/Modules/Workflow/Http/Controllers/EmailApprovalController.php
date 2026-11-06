<?php

namespace App\Modules\Workflow\Http\Controllers;

use App\Core\Tenancy\TenantManager;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Services\TenantPreferences;
use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Exceptions\WorkflowException;
use App\Modules\Workflow\Models\Approval;
use App\Modules\Workflow\Services\WorkflowEngine;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Approve / reject straight from the email, without logging in. [WF-03]
 *
 * Who is answering? The approver the link was sent to: only we can make a valid signed link,
 * and we only send it to them. (Legacy used a stored `token` column for the same purpose.)
 */
class EmailApprovalController
{
    public function show(Request $request, string $tenant, int $approval): View
    {
        [$row, $approver] = $this->open($tenant, $approval);

        $document = $row->approvable;
        abort_unless($document instanceof Approvable, 404);

        return view('workflow::email.respond', [
            'approval' => $row,
            'approver' => $approver,
            'subject' => $document->approvalSubject(),
            'typeName' => $row->step->workflow->approvalType->name,
            'requester' => User::find($row->requester_user_id),
            'workflow' => $row->step->workflow,
            'actionUrl' => $request->fullUrl(),   // the same signed URL, for the form
        ]);
    }

    public function store(Request $request, string $tenant, int $approval): RedirectResponse
    {
        [$row, $approver] = $this->open($tenant, $approval);

        $data = $request->validate([
            'decision' => ['required', Rule::in([ApprovalStatus::Approved->value, ApprovalStatus::Rejected->value, ApprovalStatus::Review->value])],
            'comment' => ['nullable', 'string', 'max:1000'],
        ]);

        try {
            app(WorkflowEngine::class)->respond($row, ApprovalStatus::from($data['decision']), $approver, $data['comment'] ?? null);
        } catch (WorkflowException $e) {
            return back()->withInput()->withErrors(['decision' => $e->getMessage()]);
        }

        return redirect()->to($request->fullUrl())->with('status', __('Thank you, your answer has been saved.'));
    }

    /**
     * Switch to the company in the URL (no login, so IdentifyTenant cannot do it) and load the row.
     *
     * @return array{0: Approval, 1: User}
     */
    private function open(string $tenantCode, int $approvalId): array
    {
        $tenant = Tenant::where('code', $tenantCode)->first();
        abort_if($tenant === null, 404);
        abort_if($tenant->accessProblem() !== null, 403, (string) $tenant->accessProblem());

        app(TenantManager::class)->switchTo($tenant);
        app(TenantPreferences::class)->apply();   // company timezone

        $row = Approval::with('step.workflow.approvalType')->find($approvalId);
        abort_if($row === null, 404);

        $approver = User::where('tenant_id', $tenant->id)->find($row->approver_user_id);
        abort_if($approver === null, 404);

        app()->setLocale($approver->preferredLocale());   // the page in the approver's language
        Carbon::setLocale($approver->preferredLocale());

        return [$row, $approver];
    }
}
