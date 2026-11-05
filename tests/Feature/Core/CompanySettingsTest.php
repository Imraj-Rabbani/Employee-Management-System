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
 * Task 2.8b: Setup → Company Settings. [FND-03]
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

    public function test_the_page_opens_from_the_setup_menu(): void
    {
        $this->get('/dashboard')->assertSee(route('core.settings'));

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
            ->set('currency', 'KES')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('toast');

        app()->forgetInstance(Settings::class);
        $this->assertSame('Africa/Nairobi', settings('timezone'));
        $this->assertSame('KES', settings('currency'));

        Livewire::test(CompanySettings::class)
            ->assertSet('timezone', 'Africa/Nairobi')
            ->assertSet('currency', 'KES');
    }

    public function test_it_only_accepts_real_timezones_and_listed_currencies(): void
    {
        Livewire::test(CompanySettings::class)
            ->set('timezone', 'Mars/Olympus')
            ->set('currency', 'XYZ')
            ->call('save')
            ->assertHasErrors(['timezone', 'currency']);
    }
}
