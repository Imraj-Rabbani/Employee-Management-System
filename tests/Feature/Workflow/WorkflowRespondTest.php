<?php

namespace Tests\Feature\Workflow;

use App\Core\Tenancy\TenantManager;
use App\Core\Tenancy\TenantMigrationPaths;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Models\Role;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Enums\ApproverType;
use App\Modules\Workflow\Exceptions\WorkflowException;
use App\Modules\Workflow\Models\Approval;
use App\Modules\Workflow\Models\ApprovalType;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowStep;
use App\Modules\Workflow\Services\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantDatabases;
use Tests\Fixtures\ApprovableNote;
use Tests\TestCase;

/**
 * Task 3.4: WorkflowEngine::respond(): approve / reject / send back, next level, ApprovalCompleted. [WF-04, WF-05]
 *
 * Set-up: a 2-level workflow. Level 1 = role "Managers" (Mina), level 2 = role "Finance" (Farid).
 */
class WorkflowRespondTest extends TestCase
{
    use CreatesTenantDatabases;
    use RefreshDatabase;

    private Tenant $tenant;

    private ApprovalType $type;

    private Workflow $workflow;

    private Role $managers;

    private User $requester;

    private User $mina;     // manager, level 1

    private User $farid;    // finance, level 2

    protected function setUp(): void
    {
        parent::setUp();

        ApprovableNote::$handled = [];
        app(TenantMigrationPaths::class)->add(base_path('tests/Fixtures/TenantMigrations'));   // "notes" table
        $this->tenant = $this->createMigratedTenant();
        app(TenantManager::class)->switchTo($this->tenant);

        $this->managers = Role::create(['name' => 'Managers']);
        $finance = Role::create(['name' => 'Finance']);
        $this->requester = $this->user();
        $this->mina = $this->user($this->managers);
        $this->farid = $this->user($finance);

        $this->type = ApprovalType::create(['key' => 'note', 'name' => 'Note']);
        $this->workflow = Workflow::create(['approval_type_id' => $this->type->id, 'name' => 'Two levels']);
        foreach ([$this->managers, $finance] as $i => $role) {
            WorkflowStep::create([
                'workflow_id' => $this->workflow->id,
                'level' => $i + 1,
                'approver_type' => ApproverType::Role,
                'approver_role_id' => $role->id,
            ]);
        }
    }

    // ---------------------------------------------------------------- the happy path

    public function test_two_levels_approve_one_after_the_other_then_the_handler_runs_once(): void
    {
        $note = $this->submitted();

        $this->answer($this->rowFor($note, $this->mina), ApprovalStatus::Approved, $this->mina);

        // Level 2 is asked now, and the document is still pending.
        $level2 = $this->rowFor($note, $this->farid);
        $this->assertSame(2, $level2->level);
        $this->assertSame(ApprovalStatus::Pending, $note->approvalStatus());
        $this->assertSame([], ApprovableNote::$handled);

        $this->answer($level2, ApprovalStatus::Approved, $this->farid, 'Fine by finance');

        $this->assertSame(ApprovalStatus::Approved, $note->approvalStatus());
        $this->assertSame(['approved:'.$note->id], ApprovableNote::$handled);
        $this->assertSame('approved', $note->fresh()?->getAttribute('text'));

        $level2->refresh();
        $this->assertSame($this->farid->id, $level2->responded_by);
        $this->assertNotNull($level2->responded_at);
        $this->assertSame('Fine by finance', $level2->comment);
    }

    public function test_when_one_role_holder_approves_the_others_are_no_longer_asked(): void
    {
        $otherManager = $this->user($this->managers);
        $note = $this->submitted();
        $this->assertSame(2, $note->approvals()->pending()->where('level', 1)->count());

        $this->answer($this->rowFor($note, $this->mina), ApprovalStatus::Approved, $this->mina);

        $this->assertFalse($note->approvals()->where('approver_user_id', $otherManager->id)->exists());
        $this->assertSame(1, $note->approvals()->where('level', 1)->count());
    }

    public function test_not_chained_completes_only_after_every_level_approved(): void
    {
        $this->type->update(['is_chained' => false]);
        $note = $this->submitted();   // levels 1 and 2 are asked at once

        $this->answer($this->rowFor($note, $this->farid), ApprovalStatus::Approved, $this->farid);
        $this->assertSame([], ApprovableNote::$handled);

        $this->answer($this->rowFor($note, $this->mina), ApprovalStatus::Approved, $this->mina);
        $this->assertSame(['approved:'.$note->id], ApprovableNote::$handled);
    }

    // ---------------------------------------------------------------- reject and send back

    public function test_rejecting_ends_the_workflow_and_runs_on_rejected(): void
    {
        $note = $this->submitted();

        $this->answer($this->rowFor($note, $this->mina), ApprovalStatus::Rejected, $this->mina, 'Not now');

        $this->assertSame(ApprovalStatus::Rejected, $note->approvalStatus());
        $this->assertSame(['rejected:'.$note->id], ApprovableNote::$handled);
        $this->assertFalse($note->approvals()->where('level', 2)->exists());   // finance never asked
    }

    public function test_sending_back_waits_for_the_requester_who_can_submit_again(): void
    {
        $note = $this->submitted();

        $this->answer($this->rowFor($note, $this->mina), ApprovalStatus::Review, $this->mina, 'Add the dates');

        $this->assertSame(ApprovalStatus::Review, $note->approvalStatus());
        $this->assertSame([], ApprovableNote::$handled);   // not finished: no handler

        $note->submitForApproval($this->requester);   // a new round at level 1

        $this->assertSame(ApprovalStatus::Pending, $note->approvalStatus());
        $this->assertSame(1, $note->approvals()->pending()->sole()->level);
    }

    // ---------------------------------------------------------------- refusals

    public function test_someone_who_is_not_the_approver_cannot_answer(): void
    {
        $note = $this->submitted();

        $this->assertRefused(fn () => $this->answer($this->rowFor($note, $this->mina), ApprovalStatus::Approved, $this->farid), 'not the approver');
        $this->assertSame(ApprovalStatus::Pending, $this->rowFor($note, $this->mina)->status);
    }

    public function test_an_answered_row_cannot_be_answered_again(): void
    {
        $note = $this->submitted();
        $row = $this->rowFor($note, $this->mina);
        $this->answer($row, ApprovalStatus::Approved, $this->mina);

        $this->assertRefused(fn () => $this->answer($row, ApprovalStatus::Rejected, $this->mina, 'Changed my mind'), 'already been answered');
    }

    public function test_reject_and_send_back_need_a_reason(): void
    {
        $row = $this->rowFor($this->submitted(), $this->mina);

        $this->assertRefused(fn () => $this->answer($row, ApprovalStatus::Rejected, $this->mina, '   '), 'give a reason');
        $this->assertRefused(fn () => $this->answer($row, ApprovalStatus::Review, $this->mina), 'give a reason');
        $this->assertSame(ApprovalStatus::Pending, $row->fresh()?->status);
    }

    public function test_the_workflow_can_forbid_reject_and_send_back(): void
    {
        $this->workflow->update(['allow_reject' => false, 'allow_review' => false]);
        $row = $this->rowFor($this->submitted(), $this->mina);

        $this->assertRefused(fn () => $this->answer($row, ApprovalStatus::Rejected, $this->mina, 'No'), 'not allowed');
        $this->assertRefused(fn () => $this->answer($row, ApprovalStatus::Review, $this->mina, 'Fix it'), 'not allowed');
    }

    public function test_a_level_can_require_an_attachment_to_approve(): void
    {
        WorkflowStep::where('level', 1)->update(['attachment_required' => true]);
        $row = $this->rowFor($this->submitted(), $this->mina);

        $this->assertRefused(fn () => $this->answer($row, ApprovalStatus::Approved, $this->mina), 'attachment is required');

        app(WorkflowEngine::class)->respond($row, ApprovalStatus::Approved, $this->mina, null, 'approvals/signed.pdf');
        $this->assertSame('approvals/signed.pdf', $row->fresh()?->attachment_path);
    }

    public function test_pending_is_not_an_answer(): void
    {
        $row = $this->rowFor($this->submitted(), $this->mina);

        $this->assertRefused(fn () => $this->answer($row, ApprovalStatus::Pending, $this->mina), 'approve, reject or send back');
    }

    // ---------------------------------------------------------------- transaction

    public function test_if_the_document_handler_fails_the_answer_is_undone(): void
    {
        $note = $this->submitted('explode');   // ApprovableNote::onApproved throws for this text
        $this->answer($this->rowFor($note, $this->mina), ApprovalStatus::Approved, $this->mina);
        $level2 = $this->rowFor($note, $this->farid);

        try {
            $this->answer($level2, ApprovalStatus::Approved, $this->farid);
            $this->fail('The handler should have thrown.');
        } catch (\RuntimeException $e) {
            $this->assertSame('The handler failed.', $e->getMessage());
        }

        // Rolled back: still waiting for Farid, nothing recorded.
        $level2 = $level2->fresh();
        $this->assertSame(ApprovalStatus::Pending, $level2?->status);
        $this->assertNull($level2?->responded_at);
        $this->assertSame(ApprovalStatus::Pending, $note->approvalStatus());
    }

    // ---------------------------------------------------------------- helpers

    private function submitted(string $text = 'hello'): ApprovableNote
    {
        $note = ApprovableNote::create(['text' => $text]);
        $note->submitForApproval($this->requester);

        return $note;
    }

    /** The open row this user must answer. */
    private function rowFor(ApprovableNote $note, User $approver): Approval
    {
        return $note->approvals()->where('approver_user_id', $approver->id)->latest('id')->firstOrFail();
    }

    private function answer(Approval $row, ApprovalStatus $decision, User $by, ?string $comment = null): void
    {
        app(WorkflowEngine::class)->respond($row, $decision, $by, $comment);
    }

    private function user(?Role $role = null): User
    {
        $user = User::factory()->for($this->tenant)->create();
        $role?->assignTo($user);

        return $user;
    }

    private function assertRefused(\Closure $action, string $message): void
    {
        try {
            $action();
            $this->fail('Expected a WorkflowException.');
        } catch (WorkflowException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }
    }
}
