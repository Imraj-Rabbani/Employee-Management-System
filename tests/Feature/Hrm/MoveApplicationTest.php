<?php

namespace Tests\Feature\Hrm;

use App\Modules\Hrm\Actions\ApplyToPosting;
use App\Modules\Hrm\Actions\MoveApplication;
use App\Modules\Hrm\Enums\ApplicationStage;
use App\Modules\Hrm\Models\Applicant;
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\JobPosting;
use App\Modules\Hrm\Models\RejectionReason;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 6.5: moving applications through the pipeline, with history. [REC-04]
 */
class MoveApplicationTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private MoveApplication $move;

    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsTenantUser();
        $this->move = app(MoveApplication::class);
        $this->application = app(ApplyToPosting::class)->handle(Applicant::factory()->create(), JobPosting::factory()->open()->create(), 5);
    }

    public function test_applying_writes_the_first_history_row(): void
    {
        $row = $this->application->stageChanges()->sole();

        $this->assertNull($row->from_stage);
        $this->assertSame(ApplicationStage::Applied, $row->to_stage);
        $this->assertSame(5, $row->changed_by_user_id);
        $this->assertNotNull($this->application->fresh()->stage_changed_at);
    }

    public function test_a_move_changes_the_stage_and_adds_history(): void
    {
        $this->move->handle($this->application, ApplicationStage::Shortlisted, note: 'Strong CV', byUserId: 9);

        $this->application->refresh();
        $this->assertSame(ApplicationStage::Shortlisted, $this->application->stage);

        $last = $this->application->stageChanges->last();
        $this->assertSame(ApplicationStage::Applied, $last->from_stage);
        $this->assertSame(ApplicationStage::Shortlisted, $last->to_stage);
        $this->assertSame('Strong CV', $last->note);
        $this->assertSame(9, $last->changed_by_user_id);
        $this->assertSame(2, $this->application->stageChanges->count());
    }

    public function test_forward_only_and_skipping_is_allowed(): void
    {
        $this->move->handle($this->application, ApplicationStage::Interview);   // skips shortlisted

        $this->assertStringContainsString('cannot move back', (string) $this->move->problem($this->application, ApplicationStage::Shortlisted)['message']);
        $this->assertSame('stage', $this->move->problem($this->application, ApplicationStage::Interview)['field']);   // already there
    }

    public function test_offered_and_hired_are_not_set_by_hand(): void
    {
        foreach ([ApplicationStage::Offered, ApplicationStage::Hired, ApplicationStage::Applied] as $to) {
            $this->assertNotNull($this->move->problem($this->application, $to), $to->value);
        }

        $this->assertSame(
            [ApplicationStage::Shortlisted, ApplicationStage::Interview, ApplicationStage::Rejected, ApplicationStage::Withdrawn],
            $this->move->targetsFor($this->application),
        );
    }

    public function test_reject_needs_an_active_reason_which_is_kept_on_the_application(): void
    {
        $inactive = RejectionReason::factory()->create(['is_active' => false]);
        $reason = RejectionReason::factory()->create(['name' => 'Not enough experience']);

        $this->assertSame('rejection_reason_id', $this->move->problem($this->application, ApplicationStage::Rejected)['field']);
        $this->assertSame('rejection_reason_id', $this->move->problem($this->application, ApplicationStage::Rejected, $inactive->id)['field']);

        $this->move->handle($this->application, ApplicationStage::Rejected, $reason->id);

        $this->application->refresh();
        $this->assertSame($reason->id, $this->application->rejection_reason_id);
        $this->assertSame('Not enough experience', $this->application->stageChanges->last()->rejectionReason?->name);
    }

    public function test_a_reason_given_for_another_stage_is_not_stored(): void
    {
        $reason = RejectionReason::factory()->create();

        $this->move->handle($this->application, ApplicationStage::Shortlisted, $reason->id);

        $this->assertNull($this->application->fresh()->rejection_reason_id);
        $this->assertNull($this->application->stageChanges->last()->rejection_reason_id);
    }

    public function test_a_closed_application_cannot_move(): void
    {
        $this->move->handle($this->application, ApplicationStage::Withdrawn, RejectionReason::factory()->create()->id);

        $this->assertSame([], $this->move->targetsFor($this->application));

        $this->expectException(LogicException::class);
        $this->move->handle($this->application, ApplicationStage::Shortlisted);
    }

    public function test_a_reason_in_use_cannot_be_deleted(): void
    {
        $reason = RejectionReason::factory()->create();
        $this->move->handle($this->application, ApplicationStage::Rejected, $reason->id);

        $this->expectException(QueryException::class);
        $reason->delete();
    }
}
