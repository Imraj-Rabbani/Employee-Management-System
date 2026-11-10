<?php

namespace Tests\Feature\Hrm;

use App\Core\Tenancy\TenantManager;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Core\Models\Role;
use App\Modules\Core\Models\UserRole;
use App\Modules\Core\Notifications\UserInvited;
use App\Modules\Hrm\Livewire\Employees\InviteUser;
use App\Modules\Hrm\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * HRM → Employees → Invite user, and the /join page where the invited person registers.
 */
class InviteUserTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private Role $clerk;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->admin = $this->actingAsTenantUser();
        $this->tenant = $this->admin->tenant;

        $this->clerk = Role::create(['name' => 'HR clerk']);
        $this->clerk->syncPermissions(['hrm.employee.view', 'hrm.employee.update']);
    }

    private function invite(string $email = 'karim@acme.test', ?Role $role = null): User
    {
        Livewire::test(InviteUser::class)
            ->call('open')
            ->set('email', $email)
            ->set('roleId', $role ? (string) $role->id : '')
            ->call('invite')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        return User::where('email', $email)->sole();
    }

    private function linkFor(User $user): string
    {
        $url = null;
        Notification::assertSentTo($user, UserInvited::class, function (UserInvited $n) use (&$url) {
            $url = $n->url;

            return $n->register;
        });

        return (string) $url;
    }

    /** @return array<string, string> */
    private function details(array $overrides = []): array
    {
        return [
            'first_name' => 'Karim', 'last_name' => 'Hasan', 'code' => 'EMP-002', 'mobile' => '01700000000',
            'gender' => '1', 'birth_date' => '1995-04-01',
            'password' => 'Secret-pass-123', 'password_confirmation' => 'Secret-pass-123',
            ...$overrides,
        ];
    }

    // ---- Invite ----------------------------------------------------------------------------

    public function test_the_employees_page_has_the_invite_button(): void
    {
        $this->get(route('hrm.employees.index'))->assertOk()->assertSee('Invite user')->assertSeeLivewire(InviteUser::class);
    }

    public function test_the_role_preview_shows_what_the_role_allows(): void
    {
        Livewire::test(InviteUser::class)
            ->assertSee('Only their own record')
            ->set('roleId', (string) $this->clerk->id)
            ->assertSee('View employees')
            ->assertSee('Edit employees')
            ->assertDontSee('Delete employees')
            ->set('roleId', (string) Role::administrator()?->id)
            ->assertSee('Every screen and action of the company');
    }

    public function test_invite_creates_a_login_with_the_role_but_no_employee_yet(): void
    {
        $user = $this->invite(role: $this->clerk);

        $this->assertSame($this->tenant->id, $user->tenant_id);
        $this->assertTrue($user->hasOpenInvitation());
        $this->assertSame($this->clerk->id, $user->role()?->id);
        $this->assertFalse(Employee::withoutGlobalScopes()->where('user_id', $user->id)->exists());
        $this->assertStringContainsString('/join/'.$user->id, $this->linkFor($user));

        Livewire::test(InviteUser::class)->assertSee('Pending invitations')->assertSee('karim@acme.test')->assertSee('HR clerk');
    }

    public function test_an_email_that_already_has_a_login_is_refused(): void
    {
        User::factory()->create(['email' => 'taken@acme.test']);   // in ANY company

        Livewire::test(InviteUser::class)->set('email', 'taken@acme.test')->call('invite')->assertHasErrors('email');
    }

    public function test_only_administrators_may_give_the_administrator_role(): void
    {
        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.employee.create', 'hrm.employee.login', 'core.users.manage']);

        Livewire::test(InviteUser::class)
            ->set('email', 'boss@acme.test')
            ->set('roleId', (string) Role::administrator()?->id)
            ->call('invite')
            ->assertHasErrors('roleId');
    }

    public function test_without_the_permissions_the_invite_is_forbidden(): void
    {
        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.employee.view', 'hrm.employee.create']);

        $this->get(route('hrm.employees.index'))->assertOk()->assertDontSee('Invite user');
        Livewire::test(InviteUser::class)->assertForbidden();
    }

    // ---- Join (the link in the email) ---------------------------------------------------------

    public function test_the_invited_person_registers_becomes_an_employee_and_is_logged_in(): void
    {
        $user = $this->invite(role: $this->clerk);
        $link = $this->linkFor($user);
        Auth::logout();
        app(TenantManager::class)->forget();   // a fresh visitor: no company chosen yet

        $this->get($link)->assertOk()->assertSee('Join '.$this->tenant->name)->assertSee('Employee ID');
        $this->post($link, $this->details())->assertRedirect(route('home'));

        $this->assertAuthenticatedAs($user);
        $user->refresh();
        $this->assertSame('Karim Hasan', $user->name);
        $this->assertFalse($user->hasOpenInvitation());
        $this->assertTrue(Auth::validate(['email' => 'karim@acme.test', 'password' => 'Secret-pass-123']));

        $employee = Employee::withoutGlobalScopes()->where('user_id', $user->id)->sole();
        $this->assertSame('EMP-002', $employee->code);
        $this->assertSame('01700000000', $employee->mobile);
        $this->assertSame('1995-04-01', $employee->birth_date?->toDateString());
        $this->assertSame('karim@acme.test', $employee->office_email);

        // Works once only: the password changed, so the fingerprint no longer matches.
        Auth::logout();
        $this->get($link)->assertSee('This link no longer works');
        $this->post($link, $this->details(['code' => 'EMP-003']))->assertForbidden();
    }

    public function test_the_employee_id_must_be_free_in_the_company(): void
    {
        Employee::factory()->create(['code' => 'EMP-002']);
        $link = $this->linkFor($this->invite());
        Auth::logout();

        $this->from($link)->post($link, $this->details())->assertSessionHasErrors('code');
        $this->assertSame(1, Employee::withoutGlobalScopes()->where('code', 'EMP-002')->count());
    }

    public function test_resending_kills_the_older_link_and_cancelling_kills_all(): void
    {
        $user = $this->invite();
        $old = $this->linkFor($user);

        Notification::fake();
        Livewire::test(InviteUser::class)->call('resend', $user->id)->assertDispatched('toast');
        $new = $this->linkFor($user);

        Auth::logout();
        $this->get($old)->assertSee('This link no longer works');
        $this->get($new)->assertSee('Employee ID');

        $this->actingAs($this->admin);
        Livewire::test(InviteUser::class)->call('cancel', $user->id)->assertDispatched('toast');
        $this->assertNull(User::find($user->id));
        $this->assertFalse(UserRole::where('user_id', $user->id)->exists());

        Auth::logout();
        $this->get($new)->assertNotFound();   // the login is gone
    }

    public function test_an_inactive_company_cannot_be_joined(): void
    {
        $link = $this->linkFor($this->invite());
        $this->tenant->update(['is_active' => false]);
        Auth::logout();

        $this->get($link)->assertSee('This link no longer works');
        $this->post($link, $this->details())->assertForbidden();
    }

    public function test_another_companys_invitation_cannot_be_resent_or_cancelled_from_here(): void
    {
        $other = User::factory()->for(Tenant::factory())->create(['invited_at' => now()]);

        Livewire::test(InviteUser::class)->call('cancel', $other->id)->assertNotDispatched('toast');
        $this->assertNotNull(User::find($other->id));
    }
}
