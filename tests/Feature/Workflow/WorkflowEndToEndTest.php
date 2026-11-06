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
use App\Modules\Workflow\Listeners\NotifyApprover;
use App\Modules\Workflow\Livewire\MyApprovalsTable;
use App\Modules\Workflow\Livewire\RespondForm;
use App\Modules\Workflow\Models\Approval;
use App\Modules\Workflow\Models\ApprovalType;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowStep;
use App\Modules\Workflow\Services\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Livewire;
use Tests\Concerns\CreatesTenantDatabases;
use Tests\Fixtures\ApprovableNote;
use Tests\TestCase;

/**
 * Task 3.7: the whole workflow, end to end through the real screens, plus the gaps
 * the earlier tests did not cover (scope tie-breaks, late second answer, other companies).
 * [WF-01..06]
 *
 * Story: Rina (requester) submits. Level 1 = role "Managers" (Mina, Mahir), level 2 = role "Finance" (Farid).
 */
class WorkflowEndToEndTest extends TestCase
{
    use CreatesTenantDatabases;
    use RefreshDatabase;

    private Tenant $tenant;

    private ApprovalType $type;

    private Workflow $workflow;

    private Role $managers;

    private Role $finance;

    private User $rina;

    private User $mina;

    private User $mahir;

    private User $farid;

    protected function setUp(): void
    {
        parent::setUp();

        ApprovableNote::$handled = [];
        app(TenantMigrationPaths::class)->add(base_path('tests/Fixtures/TenantMigrations'));   // "notes" table
        $this->tenant = $this->createMigratedTenant();
        app(TenantManager::class)->switchTo($this->tenant);

        $this->managers = Role::create(['name' => 'Managers']);
        $this->finance = Role::create(['name' => 'Finance']);
        $this->rina = $this->user('Rina Requester');
        $this->mina = $this->user('Mina Manager', $this->managers);
        $this->mahir = $this->user('Mahir Manager', $this->managers);
        $this->farid = $this->user('Farid Finance', $this->finance);

        $this->type = ApprovalType::create(['key' => 'note', 'name' => 'Note']);
        $this->workflow = $this->workflow('Two levels', [$this->managers, $this->finance]);
    }

    // ---------------------------------------------------------------- end to end

    public function test_two_levels_inbox_then_email_link_then_the_requester_is_told(): void
    {
        // 1. Rina submits. Both managers are asked; finance is not yet.
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

        // Mahir is no longer asked; Farid is, and got a notification.
        $this->assertFalse($note->approvals()->where('approver_user_id', $this->mahir->id)->exists());
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

    // ---------------------------------------------------------------- scope matching: tie-breaks

    public function test_with_two_amount_workflows_the_higher_limit_that_fits_wins(): void
    {
        $this->workflow->update(['is_active' => false]);
        $any = $this->workflow('Any');
        $from10k = $this->workflow('From 10k', attributes: ['min_amount' => 10000]);
        $from50k = $this->workflow('From 50k', attributes: ['min_amount' => 50000]);
        $engine = app(WorkflowEngine::class);

        $this->assertTrue($any->is($engine->findWorkflow($this->note(['amount' => 9000]))));
        $this->assertTrue($from10k->is($engine->findWorkflow($this->note(['amount' => 20000]))));
        $this->assertTrue($from50k->is($engine->findWorkflow($this->note(['amount' => 60000]))));
    }

    public function test_unit_and_amount_together_beat_either_alone(): void
    {
        $this->workflow('Unit 3', attributes: ['scope_org_unit_id' => 3]);
        $this->workflow('Big', attributes: ['min_amount' => 1000]);
        $both = $this->workflow('Unit 3, big', attributes: ['scope_org_unit_id' => 3, 'min_amount' => 1000]);

        $this->assertTrue($both->is(app(WorkflowEngine::class)->findWorkflow($this->note(['org_unit_id' => 3, 'amount' => 5000]))));
    }

    public function test_an_employment_type_mismatch_falls_back_to_the_general_workflow(): void
    {
        $this->workflow('Contract staff', attributes: ['scope_employment_type_id' => 2]);

        $found = app(WorkflowEngine::class)->findWorkflow($this->note(['employment_type_id' => '1']));

        $this->assertTrue($this->workflow->is($found));
    }

    // ---------------------------------------------------------------- unauthorized responders

    public function test_a_second_manager_answering_after_the_first_is_refused(): void
    {
        $note = ApprovableNote::create(['text' => 'x']);
        $note->submitForApproval($this->rina);
        $mahirsRow = $this->openRowFor($note, $this->mahir);   // opened before Mina answered

        app(WorkflowEngine::class)->respond($this->openRowFor($note, $this->mina), ApprovalStatus::Approved, $this->mina);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage('already been answered');
        app(WorkflowEngine::class)->respond($mahirsRow, ApprovalStatus::Rejected, $this->mahir, 'Too late');
    }

    public function test_the_requester_cannot_approve_their_own_document_even_with_the_role(): void
    {
        $this->managers->assignTo($this->rina);
        $note = ApprovableNote::create(['text' => 'x']);
        $note->submitForApproval($this->rina);

        $this->assertFalse($note->approvals()->where('approver_user_id', $this->rina->id)->exists());

        $this->expectException(WorkflowException::class);
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

    /**
     * @param  list<Role>  $levels  one "role" level per role; none = no steps (only for findWorkflow tests)
     * @param  array<string, mixed>  $attributes
     */
    private function workflow(string $name, array $levels = [], array $attributes = []): Workflow
    {
        $workflow = Workflow::create(['approval_type_id' => $this->type->id, 'name' => $name, ...$attributes]);

        foreach ($levels as $i => $role) {
            WorkflowStep::create([
                'workflow_id' => $workflow->id,
                'level' => $i + 1,
                'approver_type' => ApproverType::Role,
                'approver_role_id' => $role->id,
            ]);
        }

        return $workflow;
    }

    /** @param array<string, mixed> $context */
    private function note(array $context): ApprovableNote
    {
        $note = ApprovableNote::create(['text' => 'scope']);
        $note->context = $context;

        return $note;
    }

    private function openRowFor(ApprovableNote $note, User $approver): Approval
    {
        return $note->approvals()->pending()->where('approver_user_id', $approver->id)->firstOrFail();
    }

    private function user(string $name, ?Role $role = null): User
    {
        $user = User::factory()->for($this->tenant)->create(['name' => $name]);
        $role?->assignTo($user);

        return $user;
    }
}
