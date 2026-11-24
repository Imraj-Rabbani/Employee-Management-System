<?php

namespace Tests\Feature\Workflow;

use App\Core\Tenancy\TenantManager;
use App\Core\Tenancy\TenantMigrationPaths;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Exceptions\WorkflowException;
use App\Modules\Workflow\Listeners\NotifyApprover;
use App\Modules\Workflow\Livewire\MyApprovalsTable;
use App\Modules\Workflow\Livewire\RespondForm;
use App\Modules\Workflow\Models\Approval;
use App\Modules\Workflow\Models\ApprovalType;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Services\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\Concerns\CreatesTenantDatabases;
use Tests\Concerns\MakesWorkflows;
use Tests\Fixtures\ApprovableNote;
use Tests\TestCase;

/**
 * The whole workflow, end to end through the real screens, plus the gaps
 * the earlier tests did not cover (a late second answer, other companies).
 *
 * Story: Rina (requester) submits. Level 1 = Specific People: Mina, level 2 = Specific People: Farid.
 */
class WorkflowEndToEndTest extends TestCase
{
    use CreatesTenantDatabases;
    use MakesWorkflows;
    use RefreshDatabase;

    private Tenant $tenant;

    private ApprovalType $type;

    private Workflow $workflow;

    private User $rina;

    private User $mina;

    private User $farid;

    protected function setUp(): void
    {
        parent::setUp();

        ApprovableNote::$handled = [];
        app(TenantMigrationPaths::class)->add(base_path('tests/Fixtures/TenantMigrations'));   // "notes" table
        $this->tenant = $this->createMigratedTenant();
        app(TenantManager::class)->switchTo($this->tenant);

        $this->rina = $this->user('Rina Requester');
        $this->mina = $this->user('Mina Manager');
        $this->farid = $this->user('Farid Finance');

        $this->type = ApprovalType::create(['key' => 'note', 'name' => 'Note']);
        $this->workflow = $this->workflowFor($this->type, [$this->mina, $this->farid]);
    }

    // ---------------------------------------------------------------- end to end

    public function test_two_levels_inbox_then_email_link_then_the_requester_is_told(): void
    {
        // 1. Rina submits. Mina (level 1) is asked; Farid (level 2) is not yet.
        $note = ApprovableNote::create(['text' => 'Team lunch']);
        $this->actingAs($this->rina);
        $note->submitForApproval();
        $this->assertSame(1, $this->mina->unreadNotifications()->count());
        $this->assertSame(0, $this->farid->unreadNotifications()->count());

        // 2. Mina approves level 1 from her inbox.
        $this->actingAs($this->mina);
        Livewire::test(MyApprovalsTable::class)->assertSee('Note: Team lunch');
        Livewire::test(RespondForm::class)
            ->dispatch('open-approval', id: $this->openRowFor($note, $this->mina)->id)
            ->set('comment', 'Fine for me')
            ->call('approve')
            ->assertHasNoErrors();

        // Level 2 is asked automatically: Farid got a notification.
        $this->assertSame(1, $this->farid->unreadNotifications()->count());

        // 3. Farid approves level 2 from the email link, without logging in.
        auth()->logout();
        $url = NotifyApprover::respondUrl($this->openRowFor($note, $this->farid)->id);
        $this->post($url, ['decision' => 'approved'])->assertRedirect($url);

        // 4. Done: the document's handler ran once, Rina was told, the timeline shows both answers.
        $this->assertSame(ApprovalStatus::Approved, $note->approvalStatus());
        $this->assertSame(['approved:'.$note->id], ApprovableNote::$handled);
        $this->assertSame('approved', $this->rina->notifications()->sole()->data['status']);

        $timeline = Blade::render('<x-approval-timeline :document="$note" />', ['note' => $note]);
        $this->assertStringContainsString('Mina Manager', $timeline);
        $this->assertStringContainsString('Fine for me', $timeline);
        $this->assertStringContainsString('Farid Finance', $timeline);
    }

    public function test_a_rejection_in_the_inbox_stops_the_workflow_and_tells_the_requester(): void
    {
        $note = ApprovableNote::create(['text' => 'Gadget']);
        $note->submitForApproval($this->rina);

        $this->actingAs($this->mina);
        Livewire::test(RespondForm::class)
            ->dispatch('open-approval', id: $this->openRowFor($note, $this->mina)->id)
            ->set('comment', 'Not in the budget')
            ->call('reject')
            ->assertHasNoErrors();

        $this->assertSame(ApprovalStatus::Rejected, $note->approvalStatus());
        $this->assertSame(['rejected:'.$note->id], ApprovableNote::$handled);
        $this->assertSame(0, $this->farid->notifications()->count());   // level 2 never asked
        $this->assertSame('rejected', $this->rina->notifications()->sole()->data['status']);
        $this->assertSame([], $note->approvals()->pending()->pluck('id')->all());
    }

    // ---------------------------------------------------------------- unauthorized responders

    public function test_a_second_answer_on_the_same_row_is_refused(): void
    {
        $note = ApprovableNote::create(['text' => 'x']);
        $note->submitForApproval($this->rina);
        $row = $this->openRowFor($note, $this->mina);   // e.g. open in two browser tabs

        app(WorkflowEngine::class)->respond($row, ApprovalStatus::Approved, $this->mina);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage('already been answered');
        app(WorkflowEngine::class)->respond($row, ApprovalStatus::Rejected, $this->mina, 'Changed my mind');
    }

    public function test_the_requester_cannot_answer_someone_elses_level(): void
    {
        $note = ApprovableNote::create(['text' => 'x']);
        $note->submitForApproval($this->rina);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage('not the approver');
        app(WorkflowEngine::class)->respond($this->openRowFor($note, $this->mina), ApprovalStatus::Approved, $this->rina);
    }

    public function test_another_company_cannot_see_or_answer_our_approvals(): void
    {
        $note = ApprovableNote::create(['text' => 'Secret']);
        $note->submitForApproval($this->rina);
        $row = $this->openRowFor($note, $this->mina);
        $url = NotifyApprover::respondUrl($row->id);

        // Same link with another company's code: the signature no longer matches.
        $other = $this->createMigratedTenant();
        $this->get(str_replace("/{$this->tenant->code}/", "/{$other->code}/", $url))->assertForbidden();

        // A login of the other company sees its own (empty) inbox, not ours.
        $outsider = User::factory()->for($other)->create();
        app(TenantManager::class)->switchTo($other);
        $this->actingAs($outsider);
        Livewire::test(MyApprovalsTable::class)->assertDontSee('Note: Secret');

        // Our row is untouched.
        app(TenantManager::class)->switchTo($this->tenant);
        $this->assertSame(ApprovalStatus::Pending, $row->fresh()?->status);
    }

    // ---------------------------------------------------------------- helpers

    private function openRowFor(ApprovableNote $note, User $approver): Approval
    {
        return $note->approvals()->pending()->where('approver_user_id', $approver->id)->firstOrFail();
    }

    private function user(string $name): User
    {
        return User::factory()->for($this->tenant)->create(['name' => $name]);
    }
}
