<?php

namespace Tests\Feature\Hrm;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * HRM → Organization and HRM → Recruitment open as tabs (config/sections.php). The sidebar item opens
 * the first tab the user may see; tabs without permission are left out.
 */
class SectionTabsTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createMigratedTenant();
    }

    public function test_recruitment_opens_the_first_tab_the_user_may_see(): void
    {
        $this->actingAsTenantUser(tenant: $this->tenant);   // Administrator
        $this->get('/hrm/recruitment')->assertRedirect(route('hrm.recruitment.requests'));

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.recruitment.manage']);
        $this->get('/hrm/recruitment')->assertRedirect(route('hrm.recruitment.postings'));

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.recruitment.setup']);
        $this->get('/hrm/recruitment')->assertRedirect(route('hrm.recruitment.setup.factors'));

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: []);
        $this->get('/hrm/recruitment')->assertForbidden();
    }

    public function test_organization_opens_the_org_chart_for_someone_who_only_views_employees(): void
    {
        $this->actingAsTenantUser(tenant: $this->tenant);
        $this->get('/hrm/organization')->assertRedirect(route('hrm.org.units'));

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.employee.view']);
        $this->get('/hrm/organization')->assertRedirect(route('hrm.org.chart'));
        $this->get('/hrm/org/chart')->assertOk()
            ->assertSee(route('hrm.org.chart'))
            ->assertDontSee(route('hrm.org.units'));   // no organization.manage → no Org units tab
    }

    public function test_tabs_without_permission_are_left_out(): void
    {
        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['hrm.recruitment.manage']);

        $this->get('/hrm/recruitment/postings')->assertOk()
            ->assertSee(route('hrm.recruitment.applicants'))
            ->assertSee(route('hrm.recruitment.reports.open-positions'))
            ->assertDontSee(route('hrm.recruitment.setup.factors'))
            ->assertDontSee(route('hrm.recruitment.requests'));
    }

    public function test_the_hrm_menu_lists_the_new_items(): void
    {
        $this->actingAsTenantUser(tenant: $this->tenant);

        $this->get('/dashboard')->assertOk()
            ->assertSeeInOrder(['Employee', 'Organization', 'Recruitment', 'Confirmation', 'Training', 'Transfer', 'Disciplinary Action', 'Separation'])
            ->assertSee(route('hrm.organization'))
            ->assertSee(route('hrm.recruitment'))
            ->assertSee('data-sidebar-handle', false)
            // Kept between wire:navigate pages; each item knows the URLs it highlights for (resources/js/app.js).
            ->assertSee('x-persist="sidebar"', false)
            ->assertSee('data-nav-paths="[&quot;\\/hrm\\/recruitment', false)
            ->assertSee('wire:current.ignore', false);
    }
}
