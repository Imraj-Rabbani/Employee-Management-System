<?php

namespace Tests\Feature\Tenancy;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * A company only reaches the modules the super admin switched on.
 */
class ModuleAccessTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    public function test_has_module_follows_the_tenant_modules_and_core_is_always_on(): void
    {
        $tenant = Tenant::factory()->make(['modules' => ['hrm']]);

        $this->assertTrue($tenant->hasModule('hrm'));
        $this->assertFalse($tenant->hasModule('payroll'));
        $this->assertFalse($tenant->hasModule('workflow'));
        $this->assertTrue($tenant->hasModule('core'));

        $this->assertFalse(Tenant::factory()->make(['modules' => null])->hasModule('hrm'));
    }

    public function test_pages_of_switched_off_modules_are_forbidden(): void
    {
        $this->userOfCompanyWith(['hrm']);

        $this->get('/hrm/employees')->assertOk();
        $this->get('/core/settings')->assertOk();
        $this->get('/payroll/runs')->assertForbidden();
        $this->get('/workflow/inbox')->assertForbidden();
    }

    public function test_switching_a_module_on_opens_its_pages(): void
    {
        $user = $this->userOfCompanyWith([]);
        $this->get('/payroll/runs')->assertForbidden();

        $user->tenant?->update(['modules' => ['payroll']]);

        $this->actingAs($user->fresh() ?? $user)->get('/payroll/runs')->assertOk();
    }

    public function test_the_sidebar_hides_switched_off_modules(): void
    {
        $this->userOfCompanyWith(['hrm']);

        $this->get('/dashboard')
            ->assertOk()
            ->assertSee('Employee')         // HRM item (label "Employee" since the HRM navigation refactor)
            ->assertSee('Users')            // Setup (core) item
            ->assertDontSee('Payroll Runs') // Payroll item
            ->assertDontSee('My Approvals'); // Workflow item
    }

    /** @param  list<string>  $modules */
    private function userOfCompanyWith(array $modules): User
    {
        // A company Administrator, so only the module switch decides.
        return $this->actingAsTenantUser(tenant: $this->createMigratedTenant(['modules' => $modules]));
    }
}
