<?php

namespace Tests\Feature\Workflow;

use App\Core\Tenancy\TenantManager;
use App\Core\Tenancy\TenantMigrationPaths;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Models\Role;
use App\Modules\Workflow\Enums\ApprovalStatus;
use App\Modules\Workflow\Enums\ApproverType;
use App\Modules\Workflow\Listeners\NotifyApprover;
use App\Modules\Workflow\Models\Approval;
use App\Modules\Workflow\Models\ApprovalType;
use App\Modules\Workflow\Models\Workflow;
use App\Modules\Workflow\Models\WorkflowStep;
use App\Modules\Workflow\Notifications\ApprovalDecided;
use App\Modules\Workflow\Notifications\ApprovalNeeded;
use App\Modules\Workflow\Services\WorkflowEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\Concerns\CreatesTenantDatabases;
use Tests\Fixtures\ApprovableNote;
use Tests\TestCase;

/**
 * Task 3.5: in-app + email notifications, and approving from the email with a signed link. [WF-03]
 *
 * Set-up: level 1 = role "Managers" (Mina), level 2 = role "Finance" (Farid).
 */
class WorkflowNotificationsTest extends TestCase
{
    use CreatesTenantDatabases;
    use RefreshDatabase;

    private Tenant $tenant;

    private Workflow $workflow;

    private User $requester;

    private User $mina;

    private User $farid;

    protected function setUp(): void
    {
        parent::setUp();

        ApprovableNote::$handled = [];
        app(TenantMigrationPaths::class)->add(base_path('tests/Fixtures/TenantMigrations'));   // "notes" table
        $this->tenant = $this->createMigratedTenant();
        app(TenantManager::class)->switchTo($this->tenant);

        $managers = Role::create(['name' => 'Managers']);
        $finance = Role::create(['name' => 'Finance']);
        $this->requester = $this->user(null, 'Rina Requester');
        $this->mina = $this->user($managers, 'Mina Manager');
        $this->farid = $this->user($finance, 'Farid Finance');

        $type = ApprovalType::create(['key' => 'note', 'name' => 'Note']);
        $this->workflow = Workflow::create(['approval_type_id' => $type->id, 'name' => 'Two levels']);
        foreach ([$managers, $finance] as $i => $role) {
            WorkflowStep::create([
                'workflow_id' => $this->workflow->id,
                'level' => $i + 1,
                'approver_type' => ApproverType::Role,
                'approver_role_id' => $role->id,
            ]);
        }
    }

    // ---------------------------------------------------------------- who is notified

    public function test_submitting_tells_the_level_one_approver_in_app_and_by_email(): void
    {
        Notification::fake();

        $this->submitted();

        Notification::assertSentTo($this->mina, ApprovalNeeded::class, function (ApprovalNeeded $n, array $channels) {
            return $channels === ['database', 'mail'] && $n->subject === 'Note: hello' && $n->level === 1;
        });
        Notification::assertNotSentTo($this->farid, ApprovalNeeded::class);   // level 2 waits
    }

    public function test_with_send_email_off_only_the_in_app_notification_is_sent(): void
    {
        Notification::fake();
        $this->workflow->update(['send_email' => false]);

        $this->submitted();

        Notification::assertSentTo($this->mina, ApprovalNeeded::class, fn ($n, array $channels) => $channels === ['database']);
    }

    public function test_the_requester_hears_only_the_final_answer_and_the_next_approver_is_told(): void
    {
        Notification::fake();
        $note = $this->submitted();

        $this->answer($this->rowFor($note, $this->mina), ApprovalStatus::Approved, $this->mina);

        Notification::assertSentTo($this->farid, ApprovalNeeded::class, fn (ApprovalNeeded $n) => $n->level === 2);
        Notification::assertNotSentTo($this->requester, ApprovalDecided::class);   // level 1 of 2 is not news yet

        $this->answer($this->rowFor($note, $this->farid), ApprovalStatus::Approved, $this->farid);

        Notification::assertSentTo($this->requester, ApprovalDecided::class, fn (ApprovalDecided $n) => $n->status === ApprovalStatus::Approved);
    }

    public function test_a_rejection_tells_the_requester_who_rejected_and_why(): void
    {
        Notification::fake();
        $note = $this->submitted();

        $this->answer($this->rowFor($note, $this->mina), ApprovalStatus::Rejected, $this->mina, 'Budget is closed');

        Notification::assertSentTo($this->requester, ApprovalDecided::class, function (ApprovalDecided $n) {
            return $n->status === ApprovalStatus::Rejected
                && $n->responderName === 'Mina Manager'
                && $n->comment === 'Budget is closed';
        });
    }

    public function test_nobody_is_notified_about_an_answer_that_was_rolled_back(): void
    {
        $note = $this->submitted('explode');   // onApproved() throws at the end
        $this->answer($this->rowFor($note, $this->mina), ApprovalStatus::Approved, $this->mina);
        Notification::fake();

        try {
            $this->answer($this->rowFor($note, $this->farid), ApprovalStatus::Approved, $this->farid);
        } catch (\RuntimeException) {
            // expected
        }

        Notification::assertNothingSent();
    }

    public function test_the_in_app_notification_is_stored_for_the_bell(): void
    {
        $this->submitted();   // no fake: the sync test queue really sends it

        $stored = $this->mina->notifications()->sole();
        $this->assertSame('approval_needed', $stored->data['kind']);
        $this->assertSame('Note: hello', $stored->data['subject']);
        $this->assertNull($stored->read_at);
    }

    public function test_the_email_has_a_signed_link_to_the_approval_page(): void
    {
        Notification::fake();
        $note = $this->submitted();
        $row = $this->rowFor($note, $this->mina);

        Notification::assertSentTo($this->mina, ApprovalNeeded::class, function (ApprovalNeeded $n) use ($row) {
            $url = $n->toMail($this->mina)->actionUrl;

            return str_contains($url, "/workflow/email/{$this->tenant->code}/{$row->id}?")
                && str_contains($url, 'signature=')
                && str_contains($url, 'expires=');
        });
    }

    // ---------------------------------------------------------------- the email page (no login)

    public function test_the_signed_link_opens_the_page_without_logging_in(): void
    {
        $row = $this->rowFor($this->submitted(), $this->mina);

        $this->get(NotifyApprover::respondUrl($row->id))
            ->assertOk()
            ->assertSee('Note: hello')
            ->assertSee('Rina Requester')
            ->assertSee('Approve')
            ->assertSee('Reject');

        $this->assertGuest();
    }

    public function test_a_changed_or_unsigned_link_is_refused(): void
    {
        $row = $this->rowFor($this->submitted(), $this->mina);
        $url = NotifyApprover::respondUrl($row->id);

        $this->get(str_replace("/{$row->id}?", '/'.($row->id + 1).'?', $url))->assertForbidden();
        $this->get(strtok($url, '?'))->assertForbidden();
    }

    public function test_an_expired_link_is_refused(): void
    {
        $row = $this->rowFor($this->submitted(), $this->mina);
        $url = NotifyApprover::respondUrl($row->id);

        $this->travel(NotifyApprover::LINK_DAYS + 1)->days();

        $this->get($url)->assertForbidden();
    }

    public function test_approving_from_the_email_records_the_approver_and_shows_it_answered(): void
    {
        $row = $this->rowFor($this->submitted(), $this->mina);
        $url = NotifyApprover::respondUrl($row->id);

        $this->post($url, ['decision' => 'approved'])->assertRedirect($url);

        $row->refresh();
        $this->assertSame(ApprovalStatus::Approved, $row->status);
        $this->assertSame($this->mina->id, $row->responded_by);

        $this->get($url)->assertOk()->assertSee('already been answered')->assertDontSee('Send back');
    }

    public function test_rejecting_from_the_email_without_a_reason_shows_the_error(): void
    {
        $row = $this->rowFor($this->submitted(), $this->mina);
        $url = NotifyApprover::respondUrl($row->id);

        $this->from($url)->post($url, ['decision' => 'rejected'])
            ->assertRedirect($url)
            ->assertSessionHasErrors('decision');

        $this->assertSame(ApprovalStatus::Pending, $row->fresh()?->status);
    }

    public function test_the_link_stops_working_when_the_company_is_inactive(): void
    {
        $row = $this->rowFor($this->submitted(), $this->mina);
        $url = NotifyApprover::respondUrl($row->id);

        $this->tenant->update(['is_active' => false]);

        $this->get($url)->assertForbidden();
    }

    // ---------------------------------------------------------------- helpers

    private function submitted(string $text = 'hello'): ApprovableNote
    {
        $note = ApprovableNote::create(['text' => $text]);
        $note->submitForApproval($this->requester);

        return $note;
    }

    private function rowFor(ApprovableNote $note, User $approver): Approval
    {
        return $note->approvals()->where('approver_user_id', $approver->id)->latest('id')->firstOrFail();
    }

    private function answer(Approval $row, ApprovalStatus $decision, User $by, ?string $comment = null): void
    {
        app(WorkflowEngine::class)->respond($row, $decision, $by, $comment);
    }

    private function user(?Role $role, string $name): User
    {
        $user = User::factory()->for($this->tenant)->create(['name' => $name]);
        $role?->assignTo($user);

        return $user;
    }
}
