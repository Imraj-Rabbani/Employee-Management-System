<?php

namespace Tests\Feature\Workflow;

use App\Core\Tenancy\TenantMigrationPaths;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Livewire\NotificationBell;
use App\Modules\Core\Models\Role;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Enums\ApproverType;
use App\Modules\Workflow\Livewire\ApprovalTypeForm;
use App\Modules\Workflow\Livewire\MyApprovalsTable;
use App\Modules\Workflow\Livewire\RespondForm;
use App\Modules\Workflow\Livewire\WorkflowForm;
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
use Tests\Fixtures\ApprovableNote;
use Tests\TestCase;

/**
 * Task 3.6: Workflow Setup (types + workflows with levels), My Approvals inbox,
 * <x-approval-timeline> and the notification bell. [WF-01, WF-06]
 */
class WorkflowPagesTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private Role $managers;

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantMigrationPaths::class)->add(base_path('tests/Fixtures/TenantMigrations'));   // "notes" table
        $this->tenant = $this->createMigratedTenant();
        $this->actingAsTenantUser(tenant: $this->tenant);   // Administrator
        $this->managers = Role::create(['name' => 'Managers']);
    }

    // ---------------------------------------------------------------- Workflow Setup

    public function test_the_setup_page_needs_the_permission(): void
    {
        $this->get('/dashboard')->assertSee(route('workflow.setup'));
        $this->get('/workflow/setup')->assertOk()->assertSee('New workflow')->assertSee('Approval types');

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: []);
        $this->get('/workflow/setup')->assertForbidden();
    }

    public function test_an_approval_type_is_created_and_its_key_cannot_change(): void
    {
        Livewire::test(ApprovalTypeForm::class)
            ->dispatch('create-approval-type')
            ->set('key', 'Business Trip')
            ->set('name', 'Business trip')
            ->call('save')
            ->assertHasErrors(['key' => 'regex'])
            ->set('key', 'business_trip')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('approval-type-saved');

        $type = ApprovalType::forKey('business_trip');
        $this->assertNotNull($type);

        Livewire::test(ApprovalTypeForm::class)
            ->dispatch('edit-approval-type', id: $type->id)
            ->set('key', 'hacked')
            ->set('name', 'Business trip request')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['business_trip', 'Business trip request'], [$type->fresh()?->key, $type->fresh()?->name]);
    }

    public function test_a_workflow_is_created_with_its_levels_in_order(): void
    {
        $type = $this->type();
        $finance = Role::create(['name' => 'Finance']);

        Livewire::test(WorkflowForm::class)
            ->dispatch('create-workflow')
            ->assertCount('steps', 1)
            ->set('approvalTypeId', (string) $type->id)
            ->set('name', 'Notes')
            ->set('minAmount', '5000')
            ->set('steps.0.approver_role_id', (string) $finance->id)
            ->call('addStep')
            ->set('steps.1.approver_role_id', (string) $this->managers->id)
            ->set('steps.1.attachment_required', true)
            ->call('moveUp', 1)   // managers first
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('workflow-saved');

        $workflow = Workflow::with('steps')->sole();
        $this->assertSame('5000.00', $workflow->min_amount);
        $this->assertSame([$this->managers->id, $finance->id], $workflow->steps->pluck('approver_role_id')->all());
        $this->assertSame([1, 2], $workflow->steps->pluck('level')->all());
        $this->assertTrue($workflow->steps->first()?->attachment_required);
    }

    public function test_a_level_needs_a_role_and_employee_based_approvers_are_not_offered_yet(): void
    {
        Livewire::test(WorkflowForm::class)
            ->dispatch('create-workflow')
            ->set('approvalTypeId', (string) $this->type()->id)
            ->set('name', 'Notes')
            ->call('save')
            ->assertHasErrors(['steps.0.approver_role_id' => 'required'])
            ->set('steps.0.approver_role_id', (string) $this->managers->id)
            ->set('steps.0.approver_type', ApproverType::Supervisor->value)
            ->call('save')
            ->assertHasErrors(['steps.0.approver_type' => 'in']);

        $this->assertSame(0, Workflow::count());
    }

    public function test_removing_a_level_with_approval_history_is_refused(): void
    {
        $workflow = $this->workflow(levels: 2);
        $this->member('Mina');
        $this->submittedNote();
        $level1 = Approval::sole();
        app(WorkflowEngine::class)->respond($level1, ApprovalStatus::Approved, $this->userById($level1->approver_user_id));
        // Level 2 now has an approval row.

        Livewire::test(WorkflowForm::class)
            ->dispatch('edit-workflow', id: $workflow->id)
            ->assertCount('steps', 2)
            ->call('removeStep', 1)
            ->call('save')
            ->assertHasErrors('steps');

        $this->assertSame(2, $workflow->steps()->count());
    }

    public function test_removing_an_unused_level_works(): void
    {
        $workflow = $this->workflow(levels: 2);

        Livewire::test(WorkflowForm::class)
            ->dispatch('edit-workflow', id: $workflow->id)
            ->call('removeStep', 1)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame([1], $workflow->steps()->pluck('level')->all());
    }

    // ---------------------------------------------------------------- My Approvals

    public function test_the_inbox_shows_only_what_waits_for_me_and_needs_no_permission(): void
    {
        $this->workflow();
        $mina = $this->member('Mina');
        $note = $this->submittedNote('Mine to answer');

        $this->actingAs($mina);
        $this->get('/dashboard')->assertSee(route('workflow.inbox'));
        $this->get('/workflow/inbox')->assertOk();

        Livewire::test(MyApprovalsTable::class)->assertSee('Note: Mine to answer')->assertSee('Pending');

        // Someone else of the same role sees it too (own row); a stranger does not.
        $this->actingAs($this->member('Not a manager', manager: false));
        Livewire::test(MyApprovalsTable::class)->assertDontSee('Note: Mine to answer');

        $this->assertSame(ApprovalStatus::Pending, $note->approvalStatus());
    }

    public function test_answered_rows_move_to_the_answered_filter(): void
    {
        $this->workflow();
        $mina = $this->member('Mina');
        $this->submittedNote('Done one');
        $this->actingAs($mina);

        Livewire::test(RespondForm::class)
            ->dispatch('open-approval', id: Approval::where('approver_user_id', $mina->id)->value('id'))
            ->call('approve')
            ->assertHasNoErrors()
            ->assertDispatched('approval-answered');

        // The last level approved, so the fixture's onApproved() renamed the note to "approved".
        Livewire::test(MyApprovalsTable::class)->assertDontSee('Note: approved');
        Livewire::test(MyApprovalsTable::class)->set('filters.show', 'answered')->assertSee('Note: approved')->assertSee('Approved');
    }

    public function test_reject_without_a_reason_shows_the_error_in_the_modal(): void
    {
        $this->workflow();
        $mina = $this->member('Mina');
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
        $this->workflow();
        WorkflowStep::query()->update(['attachment_required' => true]);
        $mina = $this->member('Mina');
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
        $this->workflow();
        $this->member('Mina');
        $this->submittedNote();
        $this->actingAs($this->member('Nosy', manager: false));

        // Livewire turns the ModelNotFoundException into a 404 answer.
        Livewire::test(RespondForm::class)->dispatch('open-approval', id: Approval::value('id'))->assertNotFound();
    }

    // ---------------------------------------------------------------- timeline and bell

    public function test_the_timeline_lists_each_level_with_names_and_comments(): void
    {
        $this->workflow();
        $mina = $this->member('Mina Manager');
        $note = $this->submittedNote();
        app(WorkflowEngine::class)->respond(Approval::sole(), ApprovalStatus::Review, $mina, 'Please add the date');

        $html = Blade::render('<x-approval-timeline :document="$note" />', ['note' => $note]);

        $this->assertStringContainsString('Mina Manager', $html);
        $this->assertStringContainsString('Sent back', $html);
        $this->assertStringContainsString('Please add the date', $html);
    }

    public function test_the_bell_counts_unread_and_opening_one_marks_it_read(): void
    {
        $this->workflow();
        $mina = $this->member('Mina');
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

    /** A workflow of the "note" type whose levels are all role "Managers". */
    private function workflow(int $levels = 1): Workflow
    {
        $workflow = Workflow::create(['approval_type_id' => $this->type()->id, 'name' => 'Notes']);

        for ($level = 1; $level <= $levels; $level++) {
            WorkflowStep::create([
                'workflow_id' => $workflow->id,
                'level' => $level,
                'approver_type' => ApproverType::Role,
                'approver_role_id' => $this->managers->id,
            ]);
        }

        return $workflow;
    }

    /** A login of this company; a manager (can approve the test workflows) unless $manager is false. */
    private function member(string $name, bool $manager = true): User
    {
        $user = User::factory()->for($this->tenant)->create(['name' => $name]);
        if ($manager) {
            $this->managers->assignTo($user);
        }

        return $user;
    }

    private function submittedNote(string $text = 'hello'): ApprovableNote
    {
        $note = ApprovableNote::create(['text' => $text]);
        $note->submitForApproval(User::factory()->for($this->tenant)->create(['name' => 'Rina Requester']));

        return $note;
    }

    private function userById(?int $id): User
    {
        return User::findOrFail($id);
    }
}
