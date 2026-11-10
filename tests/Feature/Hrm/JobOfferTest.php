<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Actions\ApplyToPosting;
use App\Modules\Hrm\Actions\MakeOffer;
use App\Modules\Hrm\Actions\MoveApplication;
use App\Modules\Hrm\Enums\ApplicationStage;
use App\Modules\Hrm\Enums\OfferStatus;
use App\Modules\Hrm\Models\Applicant;
use App\Modules\Hrm\Models\Application;
use App\Modules\Hrm\Models\Grade;
use App\Modules\Hrm\Models\JobOffer;
use App\Modules\Hrm\Models\JobPosting;
use App\Modules\Hrm\Models\ManpowerRequest;
use App\Modules\Hrm\Models\RejectionReason;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Enums\ApproverType;
use App\Modules\Workflow\Models\Approval;
use App\Modules\Workflow\Models\ApprovalType;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowStep;
use App\Modules\Workflow\Services\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use LogicException;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 6.7: job offers (rules, salary comparison, approval → Offered, answer). [REC-06]
 */
class JobOfferTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $hr;

    private MakeOffer $offers;

    private Application $application;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->hr = $this->actingAsTenantUser();
        $this->tenant = $this->hr->tenant;
        $this->offers = app(MakeOffer::class);

        $request = ManpowerRequest::factory()->approved()->create(['salary_min' => 35000, 'salary_max' => 50000]);
        $this->application = app(ApplyToPosting::class)->handle(
            Applicant::factory()->create(['expected_salary' => 45000]),
            JobPosting::factory()->for($request)->open()->create(),
        );
        app(MoveApplication::class)->handle($this->application, ApplicationStage::Interview);
    }

    /** @param  array<string, mixed>  $values */
    private function draft(array $values = []): JobOffer
    {
        return $this->offers->create($this->application, [
            'gross_salary' => 40000,
            'designation_id' => $this->application->jobPosting->manpowerRequest->designation_id,
            'joining_date' => today()->addMonth()->toDateString(),
            ...$values,
        ], $this->hr->id);
    }

    /** A one-level workflow answered by a second administrator (never the requester). */
    private function approver(): User
    {
        $workflow = Workflow::create(['approval_type_id' => ApprovalType::forKey('job_offer')?->id, 'name' => 'Offers']);
        WorkflowStep::create(['workflow_id' => $workflow->id, 'level' => 1, 'approver_type' => ApproverType::Role, 'approver_role_id' => Role::administrator()?->id]);
        $approver = User::factory()->for($this->tenant)->create();
        Role::administrator()?->assignTo($approver);

        return $approver;
    }

    public function test_the_approval_type_is_registered(): void
    {
        $this->assertSame(JobOffer::class, ApprovalType::forKey('job_offer')?->model_class);
    }

    public function test_a_new_offer_is_a_draft_and_only_one_live_offer_at_a_time(): void
    {
        $offer = $this->draft();

        $this->assertSame(OfferStatus::Draft, $offer->status());
        $this->assertTrue($offer->isEditable());
        $this->assertStringContainsString('already an offer', (string) $this->offers->problem($this->application));

        $this->expectException(LogicException::class);
        $this->draft();
    }

    public function test_the_salary_is_compared_with_grade_band_request_and_expected_salary(): void
    {
        $grade = Grade::factory()->create(['name' => 'G5', 'salary_min' => 30000, 'salary_max' => 38000]);
        $offer = $this->draft(['gross_salary' => 40000, 'grade_id' => $grade->id]);

        $rows = collect($offer->fresh()->comparisons());

        $this->assertSame(['above', 'inside', 'below'], $rows->pluck('verdict')->all());   // band ≤ 38,000 · 35–50k · expects 45,000
        $this->assertSame('30,000 – 38,000', $rows[0]['range']);
        $this->assertSame('35,000 – 50,000', $rows[1]['range']);
        $this->assertSame('45,000', $rows[2]['range']);

        $offer->update(['grade_id' => null]);
        $this->assertNull($offer->fresh()->comparisons()[0]['verdict']);   // no grade: nothing to compare
    }

    public function test_approval_moves_the_application_to_offered(): void
    {
        $approver = $this->approver();
        $offer = $this->draft(['gross_salary' => 42000]);

        $offer->submitForApproval($this->hr);
        $this->assertSame(OfferStatus::PendingApproval, $offer->fresh()->status());
        $this->assertFalse($offer->fresh()->isEditable());
        $this->assertSame(42000.0, $offer->approvalContext()['amount']);

        app(WorkflowEngine::class)->respond(Approval::sole(), ApprovalStatus::Approved, $approver);

        $offer->refresh();
        $this->assertSame(OfferStatus::Approved, $offer->status());
        $this->assertSame(ApplicationStage::Offered, $this->application->fresh()->stage);
        $this->assertStringContainsString('approved', (string) $this->application->stageChanges()->get()->last()->note);
    }

    public function test_not_approved_leaves_the_application_and_allows_a_new_offer(): void
    {
        $approver = $this->approver();
        $offer = $this->draft();
        $offer->submitForApproval($this->hr);
        app(WorkflowEngine::class)->respond(Approval::sole(), ApprovalStatus::Rejected, $approver, 'Too high');

        $this->assertSame(OfferStatus::Rejected, $offer->fresh()->status());
        $this->assertSame(ApplicationStage::Interview, $this->application->fresh()->stage);
        $this->assertNull($this->offers->problem($this->application));
    }

    public function test_accept_and_decline(): void
    {
        $offer = JobOffer::factory()->for($this->application)->approved()->create();

        $this->offers->letterSent($offer);
        $this->assertSame(OfferStatus::LetterSent, $offer->fresh()->status());

        $this->offers->accept($offer, today());
        $this->assertSame(OfferStatus::Accepted, $offer->fresh()->status());

        $this->expectException(LogicException::class);
        $this->offers->accept($offer->fresh(), today());   // answered once only
    }

    public function test_declining_withdraws_the_application_with_the_reason(): void
    {
        $offer = JobOffer::factory()->for($this->application)->approved()->create();
        $reason = RejectionReason::factory()->create(['name' => 'Took another job']);

        try {
            $this->offers->decline($offer, today(), null);
            $this->fail('Declined without a reason.');
        } catch (LogicException) {
        }

        $this->offers->decline($offer, today(), $reason->id, 'Better pay elsewhere', $this->hr->id);

        $this->assertSame(OfferStatus::Declined, $offer->fresh()->status());
        $this->application->refresh();
        $this->assertSame(ApplicationStage::Withdrawn, $this->application->stage);
        $this->assertSame($reason->id, $this->application->rejection_reason_id);
    }

    public function test_offered_still_cannot_be_chosen_by_hand(): void
    {
        $this->assertNotNull(app(MoveApplication::class)->problem($this->application, ApplicationStage::Offered));
        $this->assertNull(app(MoveApplication::class)->problem($this->application, ApplicationStage::Offered, byHand: false));
    }
}
