<?php

namespace Tests\Feature\Tenancy;

use App\Core\Plugins\PluginRegistry;
use App\Core\Tenancy\TenantManager;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\Fixtures\Plugins\BangladeshTax;
use Tests\Fixtures\Plugins\DefaultTax;
use Tests\Fixtures\Plugins\StageOne;
use Tests\Fixtures\Plugins\StageTwo;
use Tests\TestCase;

/**
 * Task 2.6f: each company can pick its own plugins.
 */
class PluginRegistryTest extends TestCase
{
    use RefreshDatabase;

    private PluginRegistry $registry;

    protected function setUp(): void
    {
        parent::setUp();

        // A fresh registry, so the real Payroll slots don't get in the way.
        $this->registry = new PluginRegistry;
        $this->registry->slot('tax', 'Tax calculator');
        $this->registry->add('tax', 'TaxBangladesh', 'Bangladesh', BangladeshTax::class);
        $this->registry->slot('stages', 'Extra stages', multiple: true);
        $this->registry->add('stages', 'one', 'Stage one', StageOne::class);
        $this->registry->add('stages', 'two', 'Stage two', StageTwo::class);
    }

    public function test_the_payroll_module_declares_its_slots(): void
    {
        $this->assertSame(['tax', 'bank_file', 'payroll_stages'], array_keys(app(PluginRegistry::class)->slots()));
    }

    public function test_it_resolves_the_current_tenants_plugin_or_the_default(): void
    {
        $withPlugin = Tenant::factory()->create(['plugins' => ['tax' => 'TaxBangladesh']]);
        $without = Tenant::factory()->create(['plugins' => null]);
        $tenants = app(TenantManager::class);

        $tenants->switchTo($withPlugin);
        $this->assertInstanceOf(BangladeshTax::class, $this->registry->resolve('tax', DefaultTax::class));

        $tenants->switchTo($without);
        $this->assertInstanceOf(DefaultTax::class, $this->registry->resolve('tax', DefaultTax::class));

        $tenants->forget();
        $this->assertInstanceOf(DefaultTax::class, $this->registry->resolve('tax', DefaultTax::class));
    }

    public function test_an_unknown_saved_plugin_falls_back_to_the_default(): void
    {
        $tenant = Tenant::factory()->make(['plugins' => ['tax' => 'RemovedPlugin']]);

        $this->assertInstanceOf(DefaultTax::class, $this->registry->resolve('tax', DefaultTax::class, $tenant));
    }

    public function test_multiple_choice_slots_keep_the_chosen_order(): void
    {
        $tenant = Tenant::factory()->make(['plugins' => ['stages' => ['two', 'unknown', 'one']]]);

        $stages = $this->registry->resolveMany('stages', $tenant);

        $this->assertCount(2, $stages);
        $this->assertInstanceOf(StageTwo::class, $stages[0]);
        $this->assertInstanceOf(StageOne::class, $stages[1]);
        $this->assertSame([], $this->registry->resolveMany('stages', Tenant::factory()->make(['plugins' => null])));
    }

    public function test_rules_only_accept_registered_plugins(): void
    {
        $rules = $this->registry->rules();

        $this->assertTrue(Validator::make(['plugins' => ['tax' => 'TaxBangladesh', 'stages' => ['one']]], $rules)->passes());
        $this->assertTrue(Validator::make(['plugins' => ['tax' => null]], $rules)->passes());
        $this->assertFalse(Validator::make(['plugins' => ['tax' => 'Hacked']], $rules)->passes());
        $this->assertFalse(Validator::make(['plugins' => ['stages' => ['three']]], $rules)->passes());
    }

    public function test_clean_drops_empty_choices_and_unknown_slots(): void
    {
        $this->assertSame(
            ['stages' => ['one']],
            $this->registry->clean(['tax' => '', 'stages' => ['one'], 'nonsense' => 'x']),
        );
        $this->assertSame([], $this->registry->clean(['tax' => null, 'stages' => []]));
    }
}
