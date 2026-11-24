<?php

namespace Tests\Feature\Core;

use App\Models\Tenant;
use App\Modules\Core\Livewire\CompanySettings;
use App\Modules\Core\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Global Config → Company settings.
 */
class CompanySettingsTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createMigratedTenant();
        $this->tenant->update(['name' => 'Acme Garments']);
        $this->actingAsTenantUser(tenant: $this->tenant);
    }

    public function test_the_page_opens_from_global_config(): void
    {
        $this->get('/dashboard')->assertSee(route('core.config'));

        $this->get('/core/settings')
            ->assertOk()
            ->assertSeeLivewire(CompanySettings::class)
            ->assertSee('Acme Garments');
    }

    public function test_it_shows_the_defaults_for_a_new_company(): void
    {
        Livewire::test(CompanySettings::class)
            ->assertSet('timezone', 'Asia/Dhaka')
            ->assertSet('currency', 'BDT');
    }

    public function test_it_saves_the_settings(): void
    {
        Livewire::test(CompanySettings::class)
            ->set('timezone', 'Africa/Nairobi')
            ->set('currency', 'KSh')
            ->set('fiscalYear', 'FY 2026/27')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('core.settings'));

        app()->forgetInstance(Settings::class);
        $this->assertSame('Africa/Nairobi', settings('timezone'));
        $this->assertSame('KSh', settings('currency'));
        $this->assertSame('FY 2026/27', settings('fiscal_year'));

        Livewire::test(CompanySettings::class)
            ->assertSet('timezone', 'Africa/Nairobi')
            ->assertSet('currency', 'KSh')
            ->assertSet('fiscalYear', 'FY 2026/27');

        // The top bar shows the fiscal year exactly as typed.
        $this->get('/dashboard')->assertSee('FY FY 2026/27');
    }

    public function test_it_only_accepts_real_timezones_and_needs_a_currency_and_fiscal_year(): void
    {
        Livewire::test(CompanySettings::class)
            ->set('timezone', 'Mars/Olympus')
            ->set('currency', '')
            ->set('fiscalYear', '')
            ->call('save')
            ->assertHasErrors(['timezone', 'currency', 'fiscalYear']);
    }

    public function test_until_saved_the_fiscal_year_is_the_july_to_june_year_of_today(): void
    {
        $this->travelTo('2026-11-24');
        $this->assertSame('2026-2027', CompanySettings::fiscalYear());

        $this->travelTo('2027-03-01');
        $this->assertSame('2026-2027', CompanySettings::fiscalYear());

        $this->get('/dashboard')->assertSee('FY 2026-2027');
    }

    public function test_global_config_opens_the_first_allowed_tab_and_shows_the_tabs(): void
    {
        $this->get(route('core.config'))->assertRedirect(route('core.settings'));

        $this->get('/core/settings')
            ->assertSee('Global Config')
            ->assertSee(route('core.users'))
            ->assertSee(route('core.roles'));

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: ['core.users.view']);
        $this->get(route('core.config'))->assertRedirect(route('core.users'));

        $this->actingAsTenantUser(tenant: $this->tenant, permissions: []);
        $this->get(route('core.config'))->assertForbidden();
        $this->get('/dashboard')->assertDontSee(route('core.config'));
    }
}
