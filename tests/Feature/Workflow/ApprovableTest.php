<?php

namespace Tests\Feature\Workflow;

use App\Core\Tenancy\TenantManager;
use App\Core\Tenancy\TenantMigrationPaths;
use App\Modules\Workflow\Contracts\Approvable;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Enums\ApproverType;
use App\Modules\Workflow\Models\Approval;
use App\Modules\Workflow\Models\ApprovalType;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\CreatesTenantDatabases;
use Tests\Fixtures\ApprovableNote;
use Tests\TestCase;

/**
 * Task 3.2: workflow models, the Approvable interface, the HasApprovals trait and the status enum.
 */
class ApprovableTest extends TestCase
{
    use CreatesTenantDatabases;
    use RefreshDatabase;

    private WorkflowStep $level1;

    private WorkflowStep $level2;

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantMigrationPaths::class)->add(base_path('tests/Fixtures/TenantMigrations'));   // "notes" table
        app(TenantManager::class)->switchTo($this->createMigratedTenant());

        $type = ApprovalType::create(['key' => 'note', 'name' => 'Note', 'model_class' => ApprovableNote::class]);
        $workflow = Workflow::create(['approval_type_id' => $type->id, 'name' => 'Notes, any unit']);
        $this->level1 = WorkflowStep::create(['workflow_id' => $workflow->id, 'level' => 1, 'approver_type' => ApproverType::Supervisor]);
        $this->level2 = WorkflowStep::create(['workflow_id' => $workflow->id, 'level' => 2, 'approver_type' => ApproverType::Role]);
    }

    public function test_the_setup_models_are_linked_and_cast(): void
    {
        $workflow = ApprovalType::forKey('note')?->workflows()->sole();

        $this->assertNotNull($workflow);
        $this->assertSame([1, 2], $workflow->steps->pluck('level')->all());
        $this->assertSame(ApproverType::Supervisor, $workflow->steps->first()?->approver_type);
        $this->assertTrue($workflow->allow_reject);
    }

    public function test_an_approval_points_back_to_its_document_polymorphically(): void
    {
        $note = ApprovableNote::create(['text' => 'hello']);
        $approval = $this->addApproval($note, $this->level1);

        $this->assertSame(ApprovableNote::class, $approval->approvable_type);
        $this->assertTrue($note->is($approval->fresh()?->approvable));
        $this->assertInstanceOf(Approvable::class, $approval->fresh()?->approvable);
        $this->assertSame(ApprovalStatus::Pending, $approval->fresh()?->status);
    }

    public function test_a_document_only_sees_its_own_approvals(): void
    {
        $mine = ApprovableNote::create(['text' => 'mine']);
        $other = ApprovableNote::create(['text' => 'other']);
        $this->addApproval($mine, $this->level1);
        $this->addApproval($other, $this->level1);
        $this->addApproval($mine, $this->level2);

        $this->assertSame([1, 2], $mine->approvals->pluck('level')->all());
        $this->assertCount(1, $other->approvals);
    }

    public function test_status_is_null_before_submission(): void
    {
        $this->assertNull(ApprovableNote::create(['text' => 'draft'])->approvalStatus());
    }

    public function test_status_is_pending_while_any_level_waits(): void
    {
        $note = ApprovableNote::create(['text' => 'x']);
        $this->addApproval($note, $this->level1, ApprovalStatus::Approved, '2026-11-06 09:00');
        $this->addApproval($note, $this->level2);

        $this->assertSame(ApprovalStatus::Pending, $note->approvalStatus());
    }

    public function test_status_is_approved_when_every_level_approved(): void
    {
        $note = ApprovableNote::create(['text' => 'x']);
        $this->addApproval($note, $this->level1, ApprovalStatus::Approved, '2026-11-06 09:00');
        $this->addApproval($note, $this->level2, ApprovalStatus::Approved, '2026-11-06 10:00');

        $this->assertSame(ApprovalStatus::Approved, $note->approvalStatus());
    }

    public function test_status_follows_the_most_recent_answer(): void
    {
        $note = ApprovableNote::create(['text' => 'x']);
        $this->addApproval($note, $this->level1, ApprovalStatus::Approved, '2026-11-06 09:00');
        $this->addApproval($note, $this->level2, ApprovalStatus::Rejected, '2026-11-06 10:00');

        $this->assertSame(ApprovalStatus::Rejected, $note->approvalStatus());
    }

    public function test_the_status_enum_has_labels_and_badge_colours(): void
    {
        $this->assertSame('Sent back', ApprovalStatus::Review->label());
        $this->assertSame('success', ApprovalStatus::Approved->color());
        $this->assertFalse(ApprovalStatus::Pending->isFinal());
        $this->assertTrue(ApprovalStatus::Rejected->isFinal());
    }

    private function addApproval(
        ApprovableNote $note,
        WorkflowStep $step,
        ApprovalStatus $status = ApprovalStatus::Pending,
        ?string $respondedAt = null,
    ): Approval {
        return $note->approvals()->create([
            'workflow_step_id' => $step->id,
            'level' => $step->level,
            'status' => $status,
            'responded_at' => $respondedAt ? Carbon::parse($respondedAt) : null,
        ]);
    }
}
