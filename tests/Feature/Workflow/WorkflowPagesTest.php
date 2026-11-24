<?php

namespace Tests\Feature\Workflow;

use App\Core\Tenancy\TenantMigrationPaths;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Livewire\NotificationBell;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Hrm\Models\Entity;
use App\Modules\Hrm\Models\EntityType;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Enums\ApproverType;
use App\Modules\Workflow\Livewire\MyApprovalsTable;
use App\Modules\Workflow\Livewire\RespondForm;
use App\Modules\Workflow\Livewire\WorkflowForm;
use App\Modules\Workflow\Livewire\WorkflowsTable;
use App\Modules\Workflow\Models\Approval;
use App\Modules\Workflow\Models\ApprovalType;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowStep;
use App\Modules\Workflow\Services\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\Concerns\MakesWorkflows;
use Tests\Fixtures\ApprovableNote;
use Tests\TestCase;

/**
 * Workflow Setup (one row per approval type, the levels form), My Approvals inbox,
 * <x-approval-timeline> and the notification bell.
 */
class WorkflowPagesTest extends TestCase
{
    use ActsAsTenantUser;
    use MakesWorkflows;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantMigrationPaths::class)->add(base_path('tests/Fixtures/TenantMigrations'));   // "notes" table
        $this->tenant = $this->createMigratedTenant();
        $this->actingAsTenantUser(tenant: $this->tenant);   // Administrator
    }

    // ---------------------------------------------------------------- Workflow Setup

    public function test_the_setup_page_needs_the_permission(): void
    {
        $this->get('/dashboard')->assertSee(route('workflow.setup'));
        $this->get('/workflow/setup')->assertOk()->assertSee('Workflow Setup')->assertDontSee('Approval types');

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: []);
        $this->get('/workflow/setup')->assertForbidden();
    }

    public function test_the_list_shows_every_active_type_the_code_registered_with_its_levels(): void
    {
        $mina = $this->member('Mina Manager');
        $this->workflowFor($this->type(), [['approver_type' => ApproverType::Supervisor], $mina]);
        ApprovalType::create(['key' => 'hidden', 'name' => 'Switched off type', 'is_active' => false]);

        Livewire::test(WorkflowsTable::class)
            ->assertSee('Leave')                    // registered by the Hrm migration, not set up
            ->assertSee('Not set up')
            ->assertSee('Reporting supervisor')     // the note's levels
            ->assertSee('Mina Manager')
            ->assertDontSee('Switched off type')
            ->set('status', 'active')
            ->call('applyFilters')
            ->assertSee('Note')
            ->assertDontSee('Leave');
    }

    public function test_the_first_save_creates_the_types_workflow_with_its_levels_in_order(): void
    {
        $type = $this->type();
        $branch = EntityType::where('name', 'Branch')->firstOrFail();
        $gulshan = Entity::factory()->ofType($branch)->create(['name' => 'Gulshan']);
        $mina = $this->employeeOf($this->member('Mina'));

        Livewire::test(WorkflowForm::class)
            ->dispatch('edit-workflow', id: $type->id)
            ->assertSet('typeName', 'Note')
            ->assertCount('steps', 1)
            ->assertSet('steps.0.approver_type', ApproverType::Supervisor->value)
            ->call('addStep')
            ->set('steps.1.approver_type', ApproverType::EntityHead->value)
            ->set('steps.1.approver_entity_type_id', (string) $branch->id)
            ->call('addStep')
            ->set('steps.2.approver_type', ApproverType::SpecificEntityHead->value)
            ->set('steps.2.approver_entity_type_id', (string) $branch->id)
            ->set('steps.2.approver_entity_id', (string) $gulshan->id)
            ->call('addStep')
            ->set('steps.3.approver_type', ApproverType::Employee->value)
            ->set('steps.3.approver_employee_id', (string) $mina->id)
            ->set('steps.3.attachment_required', true)
            ->call('moveUp', 3)   // Mina before the Gulshan head
            ->set('allowReject', false)
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('workflow-saved');

        $workflow = Workflow::with('steps')->sole();
        $this->assertSame($type->id, $workflow->approval_type_id);
        $this->assertFalse($workflow->allow_reject);
        $this->assertSame([1, 2, 3, 4], $workflow->steps->pluck('level')->all());
        $this->assertSame(
            [ApproverType::Supervisor, ApproverType::EntityHead, ApproverType::Employee, ApproverType::SpecificEntityHead],
            $workflow->steps->pluck('approver_type')->all(),
        );
        $this->assertSame([null, $branch->id, null, $branch->id], $workflow->steps->pluck('approver_entity_type_id')->all());
        $this->assertSame([null, null, null, $gulshan->id], $workflow->steps->pluck('approver_entity_id')->all());
        $this->assertSame([null, null, $mina->id, null], $workflow->steps->pluck('approver_employee_id')->all());
        $this->assertTrue($workflow->steps[2]->attachment_required);

        // Opening it again edits the same workflow (one per type).
        Livewire::test(WorkflowForm::class)
            ->dispatch('edit-workflow', id: $type->id)
            ->assertCount('steps', 4)
            ->call('removeStep', 3)
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame(1, Workflow::count());
        $this->assertSame(3, $workflow->steps()->count());
    }

    public function test_each_type_asks_for_its_own_fields(): void
    {
        $branch = EntityType::where('name', 'Branch')->firstOrFail();
        $department = EntityType::where('name', 'Department')->firstOrFail();
        $finance = Entity::factory()->ofType($department)->create();
        $noLogin = Employee::factory()->create();

        Livewire::test(WorkflowForm::class)
            ->dispatch('edit-workflow', id: $this->type()->id)
            ->set('steps.0.approver_type', ApproverType::EntityHead->value)
            ->call('save')
            ->assertHasErrors(['steps.0.approver_entity_type_id' => 'required'])
            ->set('steps.0.approver_type', ApproverType::SpecificEntityHead->value)
            ->set('steps.0.approver_entity_type_id', (string) $branch->id)
            ->set('steps.0.approver_entity_id', (string) $finance->id)   // a Department, not a Branch
            ->call('save')
            ->assertHasErrors(['steps.0.approver_entity_id' => 'exists'])
            ->set('steps.0.approver_type', ApproverType::Employee->value)
            ->call('save')
            ->assertHasErrors(['steps.0.approver_employee_id' => 'required'])
            ->set('steps.0.approver_employee_id', (string) $noLogin->id)
            ->call('save')
            ->assertHasErrors(['steps.0.approver_employee_id' => 'exists'])
            ->set('steps.0.approver_type', 'role')
            ->call('save')
            ->assertHasErrors(['steps.0.approver_type']);

        $this->assertSame(0, Workflow::count());
    }

    public function test_a_new_entity_type_empties_the_specific_entity(): void
    {
        $branch = EntityType::where('name', 'Branch')->firstOrFail();
        $department = EntityType::where('name', 'Department')->firstOrFail();

        Livewire::test(WorkflowForm::class)
            ->dispatch('edit-workflow', id: $this->type()->id)
            ->set('steps.0.approver_type', ApproverType::SpecificEntityHead->value)
            ->set('steps.0.approver_entity_type_id', (string) $branch->id)
            ->set('steps.0.approver_entity_id', (string) Entity::factory()->ofType($branch)->create()->id)
            ->set('steps.0.approver_entity_type_id', (string) $department->id)
            ->assertSet('steps.0.approver_entity_id', '');
    }

    public function test_only_fields_of_the_chosen_type_are_kept(): void
    {
        $branch = EntityType::where('name', 'Branch')->firstOrFail();

        Livewire::test(WorkflowForm::class)
            ->dispatch('edit-workflow', id: $this->type()->id)
            ->set('steps.0.approver_type', ApproverType::EntityHead->value)
            ->set('steps.0.approver_entity_type_id', (string) $branch->id)
            ->set('steps.0.approver_type', ApproverType::Supervisor->value)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull(WorkflowStep::sole()->approver_entity_type_id);
    }

    public function test_removing_a_level_with_approval_history_is_refused(): void
    {
        $mina = $this->member('Mina');
        $workflow = $this->workflowFor($this->type(), [$mina, $mina]);
        $this->submittedNote();
        app(WorkflowEngine::class)->respond(Approval::sole(), ApprovalStatus::Approved, $mina);
        // Level 2 now has an approval row.

        Livewire::test(WorkflowForm::class)
            ->dispatch('edit-workflow', id: $this->type()->id)
            ->assertCount('steps', 2)
            ->call('removeStep', 1)
            ->call('save')
            ->assertHasErrors('steps');

        $this->assertSame(2, $workflow->steps()->count());
    }

    // ---------------------------------------------------------------- My Approvals

    public function test_the_inbox_shows_only_what_waits_for_me_and_needs_no_permission(): void
    {
        $mina = $this->member('Mina');
        $this->workflowFor($this->type(), [$mina]);
        $note = $this->submittedNote('Mine to answer');

        $this->actingAs($mina);
        $this->get('/dashboard')->assertSee(route('workflow.inbox'));
        $this->get('/workflow/inbox')->assertOk();

        Livewire::test(MyApprovalsTable::class)->assertSee('Note: Mine to answer')->assertSee('Pending');

        // Someone else does not.
        $this->actingAs($this->member('Not the approver'));
        Livewire::test(MyApprovalsTable::class)->assertDontSee('Note: Mine to answer');

        $this->assertSame(ApprovalStatus::Pending, $note->approvalStatus());
    }

    public function test_answered_rows_move_to_the_answered_filter(): void
    {
        $mina = $this->member('Mina');
        $this->workflowFor($this->type(), [$mina]);
        $this->submittedNote('Done one');
        $this->actingAs($mina);

        Livewire::test(RespondForm::class)
            ->dispatch('open-approval', id: Approval::where('approver_user_id', $mina->id)->value('id'))
            ->call('approve')
            ->assertHasNoErrors()
            ->assertDispatched('approval-answered');

        // The last level approved, so the fixture's onApproved() renamed the note to "approved".
        Livewire::test(MyApprovalsTable::class)->assertDontSee('Note: approved');
        Livewire::test(MyApprovalsTable::class)->set('show', 'answered')->assertSee('Note: approved')->assertSee('Approved');
    }

    public function test_reject_without_a_reason_shows_the_error_in_the_modal(): void
    {
        $mina = $this->member('Mina');
        $this->workflowFor($this->type(), [$mina]);
        $this->submittedNote();
        $this->actingAs($mina);

        Livewire::test(RespondForm::class)
            ->dispatch('open-approval', id: Approval::where('approver_user_id', $mina->id)->value('id'))
            ->call('reject')
            ->assertHasErrors('comment')
            ->set('comment', 'Wrong month')
            ->call('reject')
            ->assertHasNoErrors();

        $this->assertSame(ApprovalStatus::Rejected, Approval::where('approver_user_id', $mina->id)->sole()->status);
    }

    public function test_an_attachment_is_saved_on_the_company_disk(): void
    {
        $mina = $this->member('Mina');
        $this->workflowFor($this->type(), [$mina]);
        WorkflowStep::query()->update(['attachment_required' => true]);
        $this->submittedNote();
        $this->actingAs($mina);
        $id = Approval::where('approver_user_id', $mina->id)->value('id');

        Livewire::test(RespondForm::class)
            ->dispatch('open-approval', id: $id)
            ->call('approve')
            ->assertHasErrors('comment')   // "An attachment is required at this level."
            ->set('attachment', UploadedFile::fake()->create('signed.pdf', 20, 'application/pdf'))
            ->call('approve')
            ->assertHasNoErrors();

        $path = Approval::findOrFail($id)->attachment_path;
        $this->assertNotNull($path);
        $this->assertStringStartsWith('approvals/', $path);
        $this->assertTrue(Storage::disk('tenant')->exists($path));

        // Clean up: remove this test company's whole folder (storage/app/tenants/{code}).
        File::deleteDirectory(Storage::disk('tenant')->path(''));
    }

    public function test_nobody_can_open_someone_elses_approval(): void
    {
        $this->workflowFor($this->type(), [$this->member('Mina')]);
        $this->submittedNote();
        $this->actingAs($this->member('Nosy'));

        // Livewire turns the ModelNotFoundException into a 404 answer.
        Livewire::test(RespondForm::class)->dispatch('open-approval', id: Approval::value('id'))->assertNotFound();
    }

    // ---------------------------------------------------------------- timeline and bell

    public function test_the_timeline_lists_each_level_with_names_and_comments(): void
    {
        $mina = $this->member('Mina Manager');
        $this->workflowFor($this->type(), [$mina]);
        $note = $this->submittedNote();
        app(WorkflowEngine::class)->respond(Approval::sole(), ApprovalStatus::Review, $mina, 'Please add the date');

        $html = Blade::render('<x-approval-timeline :document="$note" />', ['note' => $note]);

        $this->assertStringContainsString('Mina Manager', $html);
        $this->assertStringContainsString('Sent back', $html);
        $this->assertStringContainsString('Please add the date', $html);
    }

    public function test_the_bell_counts_unread_and_opening_one_marks_it_read(): void
    {
        $mina = $this->member('Mina');
        $this->workflowFor($this->type(), [$mina]);
        $this->submittedNote('Bell test');   // sync queue: the in-app notification is stored
        $this->actingAs($mina);

        $this->get('/dashboard')->assertSeeLivewire(NotificationBell::class);

        $bell = Livewire::test(NotificationBell::class)
            ->assertViewHas('unread', 1)
            ->assertSee('Note: Bell test');

        $bell->call('open', $mina->notifications()->value('id'))->assertRedirect(route('workflow.inbox'));
        $this->assertSame(0, $mina->unreadNotifications()->count());
    }

    // ---------------------------------------------------------------- helpers

    private function type(): ApprovalType
    {
        return ApprovalType::firstOrCreate(['key' => 'note'], ['name' => 'Note']);
    }

    /** A login of this company. */
    private function member(string $name): User
    {
        return User::factory()->for($this->tenant)->create(['name' => $name]);
    }

    private function submittedNote(string $text = 'hello'): ApprovableNote
    {
        $note = ApprovableNote::create(['text' => $text]);
        $note->submitForApproval(User::factory()->for($this->tenant)->create(['name' => 'Rina Requester']));

        return $note;
    }
}
