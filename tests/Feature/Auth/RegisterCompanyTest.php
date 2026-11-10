<?php

namespace Tests\Feature\Auth;

use App\Core\Tenancy\CreateTenant;
use App\Core\Tenancy\TenantDatabase;
use App\Core\Tenancy\TenantManager;
use App\Livewire\Auth\RegisterCompany;
use App\Models\Tenant;
use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Livewire\Features\SupportTesting\Testable;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Public sign-up (/register): organisation + Administrator login + first employee, then logged in.
 */
class RegisterCompanyTest extends TestCase
{
    use RefreshDatabase;

    /** Unique per test, so the generated company code (and database) never clash. */
    private string $organisation;

    protected function setUp(): void
    {
        parent::setUp();

        $this->organisation = 'Test Org '.str()->random(8);
        RateLimiter::clear('register-company:127.0.0.1');
    }

    protected function tearDown(): void
    {
        // Sign-up really creates a database (and CREATE DATABASE commits the test transaction).
        app(TenantManager::class)->forget();

        foreach (Tenant::where('name', $this->organisation)->get() as $tenant) {
            app(TenantDatabase::class)->drop($tenant);
            $tenant->users()->delete();
            $tenant->delete();
        }

        RateLimiter::clear('register-company:127.0.0.1');

        parent::tearDown();
    }

    public function test_guests_see_the_sign_up_page_and_the_login_page_links_to_it(): void
    {
        $this->get('/register')
            ->assertOk()
            ->assertSee('Register your organisation')
            ->assertSee('Your employee ID')
            ->assertSeeLivewire(RegisterCompany::class);

        $this->get('/login')->assertSee(route('register'));
    }

    public function test_logged_in_users_are_sent_away_from_the_sign_up_page(): void
    {
        $this->actingAs(User::factory()->create())
            ->get('/register')
            ->assertRedirect();
    }

    public function test_signing_up_creates_the_organisation_the_administrator_and_the_first_employee(): void
    {
        $this->fillForm()
            ->call('register')
            ->assertHasNoErrors()
            ->assertRedirect(route('home'));

        $tenant = Tenant::where('name', $this->organisation)->sole();
        $this->assertTrue($tenant->is_active, 'Usable right away.');
        $this->assertSame(array_keys(Tenant::OPTIONAL_MODULES), $tenant->modules);
        $this->assertStringStartsWith('testorg', $tenant->code);   // made from the name

        $user = $tenant->users()->sole();
        $this->assertSame($this->email(), $user->email);
        $this->assertFalse($user->isSuperAdmin(), 'Never a platform super admin.');
        $this->assertAuthenticatedAs($user);

        app(TenantManager::class)->switchTo($tenant);
        $this->assertTrue($user->role()?->is_admin, 'Company Administrator: every screen of the company.');

        $employee = Employee::withoutGlobalScopes()->sole();
        $this->assertSame('EMP-001', $employee->code);
        $this->assertSame('Md. Rahim', $employee->first_name);
        $this->assertSame('Uddin', $employee->last_name);
        $this->assertSame($user->id, $employee->user_id);
        $this->assertSame($user->email, $employee->office_email);
        $this->assertTrue($employee->join_date->isToday());
    }

    public function test_the_new_administrator_sees_themselves_in_the_employee_list(): void
    {
        $this->fillForm()->call('register')->assertHasNoErrors();

        $this->get(route('hrm.employees.index'))->assertOk()->assertSee('Invite user');
        $this->get(route('hrm.employees.show', Employee::withoutGlobalScopes()->sole()))->assertOk()->assertSee('EMP-001');
    }

    public function test_the_form_validates_its_fields(): void
    {
        User::factory()->create(['email' => 'used@example.com']);

        Livewire::test(RegisterCompany::class)
            ->set('email', 'used@example.com')
            ->set('password', 'short')
            ->set('passwordConfirmation', 'different')
            ->call('register')
            ->assertHasErrors(['organisation', 'name', 'employeeCode', 'email', 'password', 'passwordConfirmation']);

        $this->assertGuest();
    }

    public function test_one_network_can_only_sign_up_a_few_times_an_hour(): void
    {
        for ($i = 0; $i < RegisterCompany::MAX_PER_HOUR; $i++) {
            RateLimiter::hit('register-company:127.0.0.1', 3600);
        }

        $this->fillForm()->call('register')->assertHasErrors('organisation');

        $this->assertFalse(Tenant::where('name', $this->organisation)->exists());
        $this->assertGuest();
    }

    public function test_company_codes_are_made_from_the_name_and_never_reused(): void
    {
        $create = app(CreateTenant::class);

        $this->assertSame('acmegarmentsltd', $create->codeFor('Acme Garments Ltd.'));
        $this->assertSame('c247textiles', $create->codeFor('24/7 Textiles'));
        $this->assertSame('company', $create->codeFor('!!!'));

        Tenant::factory()->create(['code' => 'acmegarmentsltd']);
        $this->assertSame('acmegarmentsltd2', $create->codeFor('Acme Garments Ltd.'));
    }

    private function email(): string
    {
        return 'owner@'.strtolower((string) preg_replace('/[^a-z0-9]/i', '', $this->organisation)).'.test';
    }

    private function fillForm(): Testable
    {
        return Livewire::test(RegisterCompany::class)
            ->set('organisation', $this->organisation)
            ->set('name', 'Md. Rahim Uddin')
            ->set('employeeCode', 'EMP-001')
            ->set('email', $this->email())
            ->set('password', 'secret-password')
            ->set('passwordConfirmation', 'secret-password');
    }
}
