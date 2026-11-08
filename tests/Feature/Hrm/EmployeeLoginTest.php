<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Notifications\UserInvited;
use App\Modules\Hrm\Livewire\Employees\LoginBox;
use App\Modules\Hrm\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 5.7: link an employee to a login, invite by email. [EMP-07]
 */
class EmployeeLoginTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private Employee $rahim;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->admin = $this->actingAsTenantUser();
        $this->tenant = $this->admin->tenant;
        $this->rahim = Employee::factory()->create(['first_name' => 'Rahim', 'last_name' => 'Uddin', 'office_email' => 'rahim@acme.test']);
    }

    private function invite(string $email = 'rahim@acme.test', string $roleId = ''): void
    {
        Livewire::test(LoginBox::class, ['employee' => $this->rahim])
            ->call('open')
            ->assertSet('email', 'rahim@acme.test')   // the office email is suggested
            ->set('email', $email)
            ->set('roleId', $roleId)
            ->call('invite')
            ->assertHasNoErrors();
    }

    /** The link from the last invitation email sent to $user. */
    private function linkFor(User $user): string
    {
        $url = null;
        Notification::assertSentTo($user, UserInvited::class, function (UserInvited $n) use (&$url) {
            $url = $n->url;

            return true;
        });

        return (string) $url;
    }

    // ---- Invite --------------------------------------------------------------------------

    public function test_invite_creates_a_login_of_this_company_and_emails_a_link(): void
    {
        $this->invite();

        $user = User::where('email', 'rahim@acme.test')->sole();
        $this->assertSame($this->tenant->id, $user->tenant_id);
        $this->assertSame('Rahim Uddin', $user->name);
        $this->assertTrue($user->hasOpenInvitation());
        $this->assertSame($user->id, $this->rahim->refresh()->user_id);
        $this->assertNull($user->role(), 'No role chosen → self service only.');
        Notification::assertSentTo($user, UserInvited::class);

        $this->get(route('hrm.employees.show', $this->rahim))->assertSee('rahim@acme.test')->assertSee('not accepted yet');
    }

    public function test_the_link_sets_the_password_logs_in_and_works_only_once(): void
    {
        $this->invite();
        $user = User::where('email', 'rahim@acme.test')->sole();
        $link = $this->linkFor($user);
        Auth::logout();

        $this->get($link)->assertOk()->assertSee('Choose a password');
        $this->post($link, ['password' => 'Secret-pass-123', 'password_confirmation' => 'Secret-pass-123'])
            ->assertRedirect(route('dashboard'));

        $this->assertAuthenticatedAs($user);
        $user->refresh();
        $this->assertFalse($user->hasOpenInvitation());
        $this->assertNotNull($user->email_verified_at);
        $this->assertTrue(Auth::validate(['email' => 'rahim@acme.test', 'password' => 'Secret-pass-123']));

        // Second time: the password changed, so the fingerprint no longer matches.
        Auth::logout();
        $this->get($link)->assertOk()->assertSee('This link no longer works');
        $this->post($link, ['password' => 'Other-pass-123', 'password_confirmation' => 'Other-pass-123'])->assertForbidden();
    }

    public function test_resending_kills_the_older_link(): void
    {
        $this->invite();
        $user = User::where('email', 'rahim@acme.test')->sole();
        $old = $this->linkFor($user);

        Notification::fake();   // forget the first email
        Livewire::test(LoginBox::class, ['employee' => $this->rahim])->call('resend')->assertDispatched('toast');
        $new = $this->linkFor($user);

        Auth::logout();
        $this->get($old)->assertSee('This link no longer works');
        $this->get($new)->assertSee('Choose a password');
    }

    public function test_a_changed_link_is_refused(): void
    {
        $this->invite();
        $other = User::factory()->for($this->tenant)->create();
        $link = $this->linkFor(User::where('email', 'rahim@acme.test')->sole());

        Auth::logout();
        $mine = '/invitation/'.$this->rahim->refresh()->user_id.'?';
        $this->assertStringContainsString($mine, $link);
        $this->get(str_replace($mine, '/invitation/'.$other->id.'?', $link))->assertForbidden();   // signature no longer fits
    }

    public function test_an_email_that_already_has_a_login_is_refused(): void
    {
        User::factory()->create(['email' => 'taken@acme.test']);   // in ANY company

        Livewire::test(LoginBox::class, ['employee' => $this->rahim])
            ->call('open')->set('email', 'taken@acme.test')->call('invite')
            ->assertHasErrors('email');
        $this->assertNull($this->rahim->refresh()->user_id);
    }

    // ---- Roles on invite -----------------------------------------------------------------

    public function test_a_role_can_be_given_with_the_invitation(): void
    {
        $role = Role::create(['name' => 'HR Officer']);

        $this->invite(roleId: (string) $role->id);

        $this->assertSame('HR Officer', User::where('email', 'rahim@acme.test')->sole()->role()?->name);
    }

    public function test_only_administrators_may_invite_as_administrator(): void
    {
        $adminRole = Role::administrator();
        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.employee.view', 'hrm.employee.login', 'core.users.manage']);

        Livewire::test(LoginBox::class, ['employee' => $this->rahim])
            ->assertViewHas('roles', fn (array $roles) => ! array_key_exists($adminRole?->id, $roles))
            ->call('open')->set('roleId', (string) $adminRole?->id)->call('invite')
            ->assertHasErrors('roleId');
    }

    // ---- Link / unlink -------------------------------------------------------------------

    public function test_an_existing_login_of_this_company_can_be_linked_and_unlinked(): void
    {
        $karim = User::factory()->for($this->tenant)->create(['name' => 'Karim']);

        Livewire::test(LoginBox::class, ['employee' => $this->rahim])
            ->call('open')
            ->assertViewHas('freeLogins', fn (array $logins) => array_key_exists($karim->id, $logins))
            ->set('mode', 'link')->set('userId', (string) $karim->id)->call('link')
            ->assertHasNoErrors();
        $this->assertSame($karim->id, $this->rahim->refresh()->user_id);

        Livewire::test(LoginBox::class, ['employee' => $this->rahim])->call('unlink');
        $this->assertNull($this->rahim->refresh()->user_id);
        $this->assertNotNull($karim->fresh(), 'Unlink keeps the login.');
    }

    public function test_logins_of_another_company_or_already_linked_ones_cannot_be_linked(): void
    {
        $foreign = User::factory()->for(Tenant::factory()->create())->create();
        $linked = User::factory()->for($this->tenant)->create();
        Employee::factory()->create()->forceFill(['user_id' => $linked->id])->save();

        foreach ([$foreign, $linked] as $user) {
            Livewire::test(LoginBox::class, ['employee' => $this->rahim])
                ->call('open')->set('mode', 'link')->set('userId', (string) $user->id)->call('link')
                ->assertHasErrors('userId');
        }
        $this->assertNull($this->rahim->refresh()->user_id);
    }

    public function test_you_cannot_unlink_your_own_login(): void
    {
        $this->rahim->forceFill(['user_id' => $this->admin->id])->save();

        Livewire::test(LoginBox::class, ['employee' => $this->rahim])->call('unlink')->assertDispatched('toast');
        $this->assertSame($this->admin->id, $this->rahim->refresh()->user_id);
    }

    // ---- Permissions ---------------------------------------------------------------------

    public function test_managing_logins_needs_its_own_permission(): void
    {
        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.employee.view', 'hrm.employee.update']);

        $this->get(route('hrm.employees.show', $this->rahim))->assertSee('No login')->assertDontSee('Give login');
        Livewire::test(LoginBox::class, ['employee' => $this->rahim])->call('open')->assertForbidden();
    }

    public function test_the_employee_sees_their_own_profile_after_accepting(): void
    {
        $this->invite();
        $user = User::where('email', 'rahim@acme.test')->sole();
        $link = $this->linkFor($user);
        Auth::logout();
        $this->post($link, ['password' => 'Secret-pass-123', 'password_confirmation' => 'Secret-pass-123']);

        $this->get(route('hrm.employees.show', $this->rahim))->assertOk()->assertSee('Rahim Uddin');
    }
}
