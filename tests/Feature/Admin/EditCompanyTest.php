<?php

namespace Tests\Feature\Admin;

use App\Core\Plugins\PluginRegistry;
use App\Livewire\Admin\EditTenantForm;
use App\Livewire\Admin\TenantsTable;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Features\SupportLockedProperties\CannotUpdateLockedPropertyException;
use Livewire\Livewire;
use Tests\Fixtures\Plugins\BangladeshTax;
use Tests\TestCase;

/**
 * Task 2.6d: Platform admin → Edit company.
 */
class EditCompanyTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->superAdmin()->create());
        $this->tenant = Tenant::factory()->create([
            'code' => 'acme', 'name' => 'Acme', 'modules' => ['hrm'], 'is_active' => true, 'expires_at' => null,
        ]);
    }

    public function test_the_list_shows_the_code_as_an_edit_link(): void
    {
        Livewire::test(TenantsTable::class)
            ->assertSeeHtml("\$dispatch('edit-tenant', { id: {$this->tenant->id} })");
    }

    public function test_the_page_includes_the_edit_form(): void
    {
        $this->get('/admin')->assertSeeLivewire(EditTenantForm::class);
    }

    public function test_opening_the_form_loads_the_company(): void
    {
        Livewire::test(EditTenantForm::class)
            ->dispatch('edit-tenant', id: $this->tenant->id)
            ->assertSet('tenantId', $this->tenant->id)
            ->assertSet('code', 'acme')
            ->assertSet('name', 'Acme')
            ->assertSet('isActive', true)
            ->assertSet('expiresAt', '')
            ->assertSet('modules', ['hrm']);
    }

    public function test_saving_updates_the_company(): void
    {
        Livewire::test(EditTenantForm::class)
            ->dispatch('edit-tenant', id: $this->tenant->id)
            ->set('name', 'Acme Garments Ltd')
            ->set('isActive', false)
            ->set('expiresAt', '2027-06-30')
            ->set('modules', ['hrm', 'payroll'])
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('tenant-saved')
            ->assertDispatched('toast');

        $tenant = $this->tenant->fresh();
        $this->assertSame('Acme Garments Ltd', $tenant?->name);
        $this->assertFalse($tenant->is_active);
        $this->assertSame('2027-06-30', $tenant->expires_at?->toDateString());
        $this->assertSame(['hrm', 'payroll'], $tenant->modules);
        $this->assertSame('acme', $tenant->code, 'The code never changes.');
    }

    public function test_clearing_the_date_removes_the_expiry(): void
    {
        $this->tenant->update(['expires_at' => '2027-01-01']);

        Livewire::test(EditTenantForm::class)
            ->dispatch('edit-tenant', id: $this->tenant->id)
            ->set('expiresAt', '')
            ->call('save');

        $this->assertNull($this->tenant->fresh()?->expires_at);
    }

    public function test_it_validates(): void
    {
        Livewire::test(EditTenantForm::class)
            ->dispatch('edit-tenant', id: $this->tenant->id)
            ->set('name', '')
            ->set('expiresAt', 'not-a-date')
            ->set('modules', ['rocket-science'])
            ->call('save')
            ->assertHasErrors(['name', 'expiresAt', 'modules.0']);
    }

    public function test_the_company_id_cannot_be_changed_from_the_browser(): void
    {
        $other = Tenant::factory()->create();

        $this->expectException(CannotUpdateLockedPropertyException::class);

        Livewire::test(EditTenantForm::class)
            ->dispatch('edit-tenant', id: $this->tenant->id)
            ->set('tenantId', $other->id);
    }

    public function test_it_saves_plugin_choices(): void
    {
        $registry = app(PluginRegistry::class);
        $registry->add('tax', 'TaxBangladesh', 'Bangladesh', BangladeshTax::class);

        Livewire::test(EditTenantForm::class)
            ->dispatch('edit-tenant', id: $this->tenant->id)
            ->assertSee('Tax calculator')
            ->assertSee('Bangladesh')
            ->set('plugins.tax', 'TaxBangladesh')
            ->set('plugins.bank_file', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(['tax' => 'TaxBangladesh'], $this->tenant->fresh()?->plugins);

        // Back to "Default" removes the choice.
        Livewire::test(EditTenantForm::class)
            ->dispatch('edit-tenant', id: $this->tenant->id)
            ->assertSet('plugins.tax', 'TaxBangladesh')
            ->set('plugins.tax', '')
            ->call('save');

        $this->assertSame([], $this->tenant->fresh()?->plugins);
    }

    public function test_it_refuses_unknown_plugins(): void
    {
        Livewire::test(EditTenantForm::class)
            ->dispatch('edit-tenant', id: $this->tenant->id)
            ->set('plugins.tax', 'NotAPlugin')
            ->call('save')
            ->assertHasErrors(['plugins.tax']);
    }

    public function test_a_company_user_cannot_use_it(): void
    {
        $this->actingAs(User::factory()->for($this->tenant)->create());

        Livewire::test(EditTenantForm::class)->assertForbidden();
    }
}
