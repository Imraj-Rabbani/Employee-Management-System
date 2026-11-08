<?php

namespace Tests\Feature\Workflow;

use App\Core\Tenancy\TenantManager;
use App\Core\Tenancy\TenantMigrationPaths;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Models\Role;
use App\Modules\Hrm\Models\EmploymentType;
use App\Modules\Hrm\Models\OrgUnit;
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
 * Task 3.3: WorkflowEngine::start() picks the workflow by scope and creates rows for the approvers.
 * Approvers are logins for now ("role" type); employee-based types come in Phase 5.
 */
class WorkflowStartTest extends TestCase
{
    use CreatesTenantDatabases;
    use RefreshDatabase;

    private Tenant $tenant;

    private ApprovalType $type;

    private Role $managers;

    private Role $finance;

    private User $requester;

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantMigrationPaths::class)->add(base_path('tests/Fixtures/TenantMigrations'));   // "notes" table
        $this->tenant = $this->createMigratedTenant();
        app(TenantManager::class)->switchTo($this->tenant);

        $this->type = ApprovalType::create(['key' => 'note', 'name' => 'Note']);
        $this->managers = Role::create(['name' => 'Managers']);
        $this->finance = Role::create(['name' => 'Finance']);
        $this->requester = $this->userWithRole($this->managers);
    }

    // ---------------------------------------------------------------- picking the workflow

    public function test_a_catch_all_workflow_is_used_when_nothing_more_specific_exists(): void
    {
        $any = $this->workflow('Any');

        $this->assertTrue($any->is(app(WorkflowEngine::class)->findWorkflow($this->note(['org_unit_id' => 7]))));
    }

    public function test_the_most_specific_matching_workflow_wins(): void
    {
        $unit = OrgUnit::factory()->create();
        $permanent = EmploymentType::factory()->create();
        $contract = EmploymentType::factory()->create();

        $this->workflow('Any');
        $unitOnly = $this->workflow('Unit', ['scope_org_unit_id' => $unit->id]);
        $unitPermanent = $this->workflow('Unit, permanent', ['scope_org_unit_id' => $unit->id, 'scope_employment_type_id' => $permanent->id]);

        $engine = app(WorkflowEngine::class);

        $this->assertTrue($unitOnly->is($engine->findWorkflow($this->note(['org_unit_id' => $unit->id, 'employment_type_id' => $contract->id]))));
        $this->assertTrue($unitPermanent->is($engine->findWorkflow($this->note(['org_unit_id' => $unit->id, 'employment_type_id' => $permanent->id]))));
    }

    public function test_a_workflow_scoped_to_another_unit_is_ignored(): void
    {
        $any = $this->workflow('Any');
        $this->workflow('Unit A', ['scope_org_unit_id' => OrgUnit::factory()->create()->id]);

        $this->assertTrue($any->is(app(WorkflowEngine::class)->findWorkflow($this->note(['org_unit_id' => OrgUnit::factory()->create()->id]))));
    }

    public function test_min_amount_picks_the_big_amount_workflow_only_above_the_limit(): void
    {
        $any = $this->workflow('Any');
        $big = $this->workflow('Big amounts', ['min_amount' => 50000]);
        $engine = app(WorkflowEngine::class);

        $this->assertTrue($any->is($engine->findWorkflow($this->note(['amount' => 49999.99]))));
        $this->assertTrue($big->is($engine->findWorkflow($this->note(['amount' => 50000]))));
        $this->assertTrue($any->is($engine->findWorkflow($this->note())));   // no amount at all
    }

    public function test_inactive_workflows_and_inactive_types_are_ignored(): void
    {
        $this->workflow('Off', ['is_active' => false]);
        $this->assertNull(app(WorkflowEngine::class)->findWorkflow($this->note()));

        $this->workflow('On');
        $this->type->update(['is_active' => false]);
        $this->assertNull(app(WorkflowEngine::class)->findWorkflow($this->note()));
    }

    // ---------------------------------------------------------------- creating approval rows

    public function test_chained_submit_asks_only_level_one_one_row_per_role_holder(): void
    {
        $a = $this->userWithRole($this->managers);
        $b = $this->userWithRole($this->managers);
        $this->userWithRole($this->finance);
        $this->workflow('Two levels', steps: [$this->managers, $this->finance]);

        $note = $this->note();
        $rows = $note->submitForApproval($this->requester);

        // The requester is a manager too, but never approves their own document.
        $this->assertEqualsCanonicalizing([$a->id, $b->id], $rows->pluck('approver_user_id')->all());
        $this->assertSame([1], $rows->pluck('level')->unique()->values()->all());
        $this->assertTrue($rows->every(fn (Approval $r) => $r->requester_user_id === $this->requester->id));
        $this->assertSame(ApprovalStatus::Pending, $note->approvalStatus());
    }

    public function test_not_chained_submit_asks_every_level_at_once(): void
    {
        $this->type->update(['is_chained' => false]);
        $this->userWithRole($this->managers);
        $this->userWithRole($this->finance);
        $this->workflow('Two levels', steps: [$this->managers, $this->finance]);

        $rows = $this->note()->submitForApproval($this->requester);

        $this->assertSame([1, 2], $rows->pluck('level')->unique()->sort()->values()->all());
    }

    public function test_the_logged_in_user_is_the_requester_by_default(): void
    {
        $this->userWithRole($this->finance);
        $this->workflow('One level', steps: [$this->finance]);
        $this->actingAs($this->requester);

        $rows = $this->note()->submitForApproval();

        $this->assertSame($this->requester->id, $rows->sole()->requester_user_id);
    }

    // ---------------------------------------------------------------- refusals (nothing is saved)

    public function test_without_a_matching_workflow_it_refuses(): void
    {
        $this->assertRefused($this->note(), 'No active approval workflow');
    }

    public function test_a_document_already_waiting_cannot_be_submitted_again(): void
    {
        $this->userWithRole($this->finance);
        $this->workflow('One level', steps: [$this->finance]);
        $note = $this->note();
        $note->submitForApproval($this->requester);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage('already waiting');
        $note->submitForApproval($this->requester);
    }

    public function test_a_level_nobody_can_approve_refuses(): void
    {
        $this->workflow('One level', steps: [$this->finance]);   // nobody has the Finance role

        $this->assertRefused($this->note(), 'Nobody can approve level 1');
    }

    public function test_a_supervisor_step_without_an_employee_finds_nobody(): void
    {
        // Employee-based approvers work since 5.8; a note has no employee, so there is no supervisor.
        $workflow = $this->workflow('Supervisor');
        WorkflowStep::create(['workflow_id' => $workflow->id, 'level' => 1, 'approver_type' => ApproverType::Supervisor]);

        $this->assertRefused($this->note(), 'Nobody can approve level 1');
    }

    public function test_logins_of_other_companies_are_never_approvers(): void
    {
        // createTenantWithDatabase() is tracked, so the other company is cleaned up after the test.
        $outsider = User::factory()->for($this->createTenantWithDatabase())->create();
        $this->finance->assignTo($outsider);   // bad data: a user_roles row for a stranger
        $this->workflow('One level', steps: [$this->finance]);

        $this->assertRefused($this->note(), 'Nobody can approve');
    }

    // ---------------------------------------------------------------- helpers

    /** @param array<string, mixed> $context */
    private function note(array $context = []): ApprovableNote
    {
        $note = ApprovableNote::create(['text' => 'hello']);
        $note->context = $context;

        return $note;
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<Role>  $steps  one "role" step per role, level 1, 2 ...
     */
    private function workflow(string $name, array $attributes = [], array $steps = []): Workflow
    {
        $workflow = Workflow::create(['approval_type_id' => $this->type->id, 'name' => $name, ...$attributes]);

        foreach ($steps as $i => $role) {
            WorkflowStep::create([
                'workflow_id' => $workflow->id,
                'level' => $i + 1,
                'approver_type' => ApproverType::Role,
                'approver_role_id' => $role->id,
            ]);
        }

        return $workflow;
    }

    private function userWithRole(Role $role): User
    {
        $user = User::factory()->for($this->tenant)->create();
        $role->assignTo($user);

        return $user;
    }

    private function assertRefused(ApprovableNote $note, string $message): void
    {
        try {
            $note->submitForApproval($this->requester);
            $this->fail('Expected a WorkflowException.');
        } catch (WorkflowException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
        }

        $this->assertSame(0, Approval::count());
    }
}
