<?php

namespace Tests\Feature\Admin;

use App\Core\Tenancy\TenantDatabase;
use App\Core\Tenancy\TenantManager;
use App\Livewire\Admin\CreateTenantForm;
use App\Livewire\Admin\TenantsTable;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Task 2.6c: Platform admin → Companies list and "New company" form.
 */
class CompaniesPageTest extends TestCase
{
    use RefreshDatabase;

    private string $code;

    protected function setUp(): void
    {
        parent::setUp();

        $this->code = 't'.strtolower(str()->random(8));
        $this->actingAs(User::factory()->superAdmin()->create());
    }

    protected function tearDown(): void
    {
        // The form really creates a database (and CREATE DATABASE commits the test transaction).
        app(TenantManager::class)->forget();

        foreach (Tenant::where('code', $this->code)->get() as $tenant) {
            app(TenantDatabase::class)->drop($tenant);
            $tenant->users()->delete();
            $tenant->delete();
        }

        parent::tearDown();
    }

    public function test_the_page_shows_the_list_and_the_new_company_button(): void
    {
        Tenant::factory()->create(['name' => 'Acme Garments']);

        $this->get('/admin')
            ->assertOk()
            ->assertSee('New company')
            ->assertSeeLivewire(TenantsTable::class)
            ->assertSeeLivewire(CreateTenantForm::class)
            ->assertSee('Acme Garments');
    }

    public function test_the_list_can_be_searched(): void
    {
        Tenant::factory()->create(['name' => 'Acme Garments']);
        Tenant::factory()->create(['name' => 'Bengal Textiles']);

        Livewire::test(TenantsTable::class)
            ->set('search', 'bengal')
            ->assertSee('Bengal Textiles')
            ->assertDontSee('Acme Garments');
    }

    public function test_the_form_creates_a_company(): void
    {
        Livewire::test(CreateTenantForm::class)
            ->set('code', strtoupper($this->code).' ')
            ->set('name', 'Test Company')
            ->set('modules', ['hrm'])
            ->set('adminName', 'Company Admin')
            ->set('adminEmail', 'admin@'.$this->code.'.test')
            ->set('adminPassword', 'secret-password')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('tenant-saved')
            ->assertDispatched('toast')
            ->assertSet('code', '');   // form is cleared for the next company

        $tenant = Tenant::where('code', $this->code)->firstOrFail();
        $this->assertSame(['hrm'], $tenant->modules);
        $this->assertTrue(app(TenantDatabase::class)->exists($tenant));
        $this->assertSame(1, $tenant->users()->count());
    }

    public function test_the_form_validates_its_fields(): void
    {
        Tenant::factory()->create(['code' => 'taken']);
        User::factory()->create(['email' => 'used@example.com']);

        Livewire::test(CreateTenantForm::class)
            ->set('code', 'taken')
            ->set('modules', ['hrm', 'rocket-science'])
            ->set('adminEmail', 'used@example.com')
            ->set('adminPassword', 'short')
            ->call('save')
            ->assertHasErrors(['code', 'name', 'modules.1', 'adminName', 'adminEmail', 'adminPassword']);

        Livewire::test(CreateTenantForm::class)
            ->set('code', '9-bad code')
            ->call('save')
            ->assertHasErrors(['code' => 'regex']);
    }

    public function test_a_company_user_cannot_use_the_admin_components(): void
    {
        $this->actingAs(User::factory()->for(Tenant::factory())->create());

        Livewire::test(TenantsTable::class)->assertForbidden();
        Livewire::test(CreateTenantForm::class)->assertForbidden();
    }
}
