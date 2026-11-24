<?php

namespace Tests\Feature\Workflow;

use App\Core\Tenancy\TenantManager;
use App\Core\Tenancy\TenantMigrationPaths;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Hrm\Models\EntityType;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Enums\ApproverType;
use App\Modules\Workflow\Exceptions\WorkflowException;
use App\Modules\Workflow\Models\Approval;
use App\Modules\Workflow\Models\ApprovalType;
use App\Modules\Workflow\Services\WorkflowEngine;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantDatabases;
use Tests\Concerns\MakesWorkflows;
use Tests\Fixtures\ApprovableNote;
use Tests\TestCase;

/**
 * WorkflowEngine::start(): uses the ONE workflow of the document's type and creates rows for the
 * approvers of the first level (all levels when the type is not chained).
 */
class WorkflowStartTest extends TestCase
{
    use CreatesTenantDatabases;
    use MakesWorkflows;
    use RefreshDatabase;

    private Tenant $tenant;

    private ApprovalType $type;

    private User $requester;

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantMigrationPaths::class)->add(base_path('tests/Fixtures/TenantMigrations'));   // "notes" table
        $this->tenant = $this->createMigratedTenant();
        app(TenantManager::class)->switchTo($this->tenant);

        $this->type = ApprovalType::create(['key' => 'note', 'name' => 'Note']);
        $this->requester = $this->login();
        ApprovableNote::$contexts = [];
    }

    // ---------------------------------------------------------------- the type's workflow

    public function test_every_document_of_the_type_uses_its_one_workflow(): void
    {
        $workflow = $this->workflowFor($this->type, [$this->login()]);
        $engine = app(WorkflowEngine::class);

        $this->assertTrue($workflow->is($engine->findWorkflow($this->note(['entity_ids' => [7], 'amount' => 99]))));
        $this->assertTrue($workflow->is($engine->findWorkflow($this->note())));
    }

    public function test_a_type_has_only_one_workflow(): void
    {
        $this->workflowFor($this->type, []);

        $this->expectException(QueryException::class);   // workflows.approval_type_id is unique
        $this->workflowFor($this->type, []);
    }

    public function test_a_switched_off_workflow_or_type_is_not_used(): void
    {
        $workflow = $this->workflowFor($this->type, [$this->login()], ['is_active' => false]);
        $this->assertNull(app(WorkflowEngine::class)->findWorkflow($this->note()));

        $workflow->update(['is_active' => true]);
        $this->type->update(['is_active' => false]);
        $this->assertNull(app(WorkflowEngine::class)->findWorkflow($this->note()));
    }

    // ---------------------------------------------------------------- creating approval rows

    public function test_chained_submit_asks_only_level_one(): void
    {
        $first = $this->login();
        $this->workflowFor($this->type, [$first, $this->login()]);

        $note = $this->note();
        $rows = $note->submitForApproval($this->requester);

        $this->assertSame([$first->id], $rows->pluck('approver_user_id')->all());
        $this->assertSame(1, $rows->sole()->level);
        $this->assertSame($this->requester->id, $rows->sole()->requester_user_id);
        $this->assertSame(ApprovalStatus::Pending, $note->approvalStatus());
    }

    public function test_not_chained_submit_asks_every_level_at_once(): void
    {
        $this->type->update(['is_chained' => false]);
        $this->workflowFor($this->type, [$this->login(), $this->login()]);

        $rows = $this->note()->submitForApproval($this->requester);

        $this->assertSame([1, 2], $rows->pluck('level')->sort()->values()->all());
    }

    public function test_the_logged_in_user_is_the_requester_by_default(): void
    {
        $this->workflowFor($this->type, [$this->login()]);
        $this->actingAs($this->requester);

        $rows = $this->note()->submitForApproval();

        $this->assertSame($this->requester->id, $rows->sole()->requester_user_id);
    }

    // ---------------------------------------------------------------- the four approver types

    public function test_reporting_supervisor_is_the_employees_supervisor(): void
    {
        $boss = $this->login();
        $employee = Employee::factory()->create(['supervisor_id' => $this->employeeOf($boss)->id]);
        $this->workflowFor($this->type, [['approver_type' => ApproverType::Supervisor]]);

        $rows = $this->note(['employee_id' => $employee->id])->submitForApproval($this->requester);

        $this->assertSame($boss->id, $rows->sole()->approver_user_id);
    }

    public function test_employees_entity_head_is_the_head_of_their_entity_of_that_type(): void
    {
        $branch = EntityType::where('name', 'Branch')->firstOrFail();
        $head = $this->login();
        $gulshan = Entity::factory()->ofType($branch)->create(['head_employee_id' => $this->employeeOf($head)->id]);
        $employee = Employee::factory()->inEntity($gulshan)->create();
        $this->workflowFor($this->type, [['approver_type' => ApproverType::EntityHead, 'approver_entity_type_id' => $branch->id]]);

        $rows = $this->note(['employee_id' => $employee->id])->submitForApproval($this->requester);

        $this->assertSame($head->id, $rows->sole()->approver_user_id);
    }

    public function test_specific_entity_head_is_the_head_of_the_chosen_entity_whoever_asks(): void
    {
        $branch = EntityType::where('name', 'Branch')->firstOrFail();
        $head = $this->login();
        $gulshan = Entity::factory()->ofType($branch)->create(['head_employee_id' => $this->employeeOf($head)->id]);
        $elsewhere = Employee::factory()->inEntity(Entity::factory()->ofType($branch)->create())->create();
        $this->workflowFor($this->type, [[
            'approver_type' => ApproverType::SpecificEntityHead, 'approver_entity_type_id' => $branch->id, 'approver_entity_id' => $gulshan->id,
        ]]);

        $rows = $this->note(['employee_id' => $elsewhere->id])->submitForApproval($this->requester);

        $this->assertSame($head->id, $rows->sole()->approver_user_id);
    }

    public function test_specific_entity_without_a_head_asks_the_parents_head(): void
    {
        $division = EntityType::factory()->create();
        $branch = EntityType::factory()->create();
        $divisionHead = $this->login();
        $dhaka = Entity::factory()->ofType($division)->create(['head_employee_id' => $this->employeeOf($divisionHead)->id]);
        $gulshan = Entity::factory()->ofType($branch)->create(['parent_id' => $dhaka->id]);   // no head
        $this->workflowFor($this->type, [[
            'approver_type' => ApproverType::SpecificEntityHead, 'approver_entity_type_id' => $branch->id, 'approver_entity_id' => $gulshan->id,
        ]]);

        $rows = $this->note()->submitForApproval($this->requester);

        $this->assertSame($divisionHead->id, $rows->sole()->approver_user_id);
    }

    public function test_the_example_three_levels_supervisor_then_branch_head_then_a_person(): void
    {
        $branch = EntityType::where('name', 'Branch')->firstOrFail();
        [$userB, $userC, $userD] = [$this->login(), $this->login(), $this->login()];
        $gulshan = Entity::factory()->ofType($branch)->create(['head_employee_id' => $this->employeeOf($userC)->id]);
        $userA = Employee::factory()->inEntity($gulshan)->create(['supervisor_id' => $this->employeeOf($userB)->id]);
        $this->workflowFor($this->type, [
            ['approver_type' => ApproverType::Supervisor],
            ['approver_type' => ApproverType::EntityHead, 'approver_entity_type_id' => $branch->id],
            $userD,
        ]);

        $note = $this->note(['employee_id' => $userA->id]);
        ApprovableNote::$contexts[$note->id] = $note->context;   // respond() loads the note again for levels 2 and 3
        $engine = app(WorkflowEngine::class);
        $note->submitForApproval($this->requester);

        foreach ([$userB, $userC, $userD] as $level => $approver) {
            $row = Approval::query()->where('status', ApprovalStatus::Pending)->sole();
            $this->assertSame([$level + 1, $approver->id], [$row->level, $row->approver_user_id]);
            $engine->respond($row, ApprovalStatus::Approved, $approver);
        }

        $this->assertSame(ApprovalStatus::Approved, $note->fresh()?->approvalStatus());
    }

    // ---------------------------------------------------------------- refusals (nothing is saved)

    public function test_without_a_workflow_it_refuses(): void
    {
        $this->assertRefused($this->note(), 'No active approval workflow');
    }

    public function test_a_workflow_without_levels_refuses(): void
    {
        $this->workflowFor($this->type, []);

        $this->assertRefused($this->note(), 'has no levels');
    }

    public function test_a_document_already_waiting_cannot_be_submitted_again(): void
    {
        $this->workflowFor($this->type, [$this->login()]);
        $note = $this->note();
        $note->submitForApproval($this->requester);

        $this->expectException(WorkflowException::class);
        $this->expectExceptionMessage('already waiting');
        $note->submitForApproval($this->requester);
    }

    public function test_the_requester_never_approves_their_own_document(): void
    {
        $this->workflowFor($this->type, [$this->requester]);

        $this->assertRefused($this->note(), 'Nobody can approve level 1');
    }

    public function test_a_chosen_employee_without_a_login_finds_nobody(): void
    {
        $this->workflowFor($this->type, [['approver_type' => ApproverType::Employee, 'approver_employee_id' => Employee::factory()->create()->id]]);

        $this->assertRefused($this->note(), 'Nobody can approve level 1');
    }

    public function test_a_supervisor_level_without_an_employee_finds_nobody(): void
    {
        // A note has no employee, so there is no supervisor.
        $this->workflowFor($this->type, [['approver_type' => ApproverType::Supervisor]]);

        $this->assertRefused($this->note(), 'Nobody can approve level 1');
    }

    // ---------------------------------------------------------------- helpers

    /** @param array<string, mixed> $context */
    private function note(array $context = []): ApprovableNote
    {
        $note = ApprovableNote::create(['text' => 'hello']);
        $note->context = $context;

        return $note;
    }

    private function login(): User
    {
        return User::factory()->for($this->tenant)->create();
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
