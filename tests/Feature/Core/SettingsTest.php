<?php

namespace Tests\Feature\Core;

use App\Core\Tenancy\TenantManager;
use App\Models\Tenant;
use App\Modules\Core\Models\Setting;
use App\Modules\Core\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Concerns\CreatesTenantDatabases;
use Tests\TestCase;

/**
 * Task 2.8a: per-company key/value settings with autoload and cache. [FND-03]
 */
class SettingsTest extends TestCase
{
    use CreatesTenantDatabases;
    use RefreshDatabase;

    private Tenant $alpha;

    private Tenant $beta;

    private TenantManager $tenants;

    protected function setUp(): void
    {
        parent::setUp();

        $this->alpha = $this->createMigratedTenant();
        $this->beta = $this->createMigratedTenant();
        $this->tenants = app(TenantManager::class);
        $this->tenants->switchTo($this->alpha);
    }

    public function test_values_keep_their_type(): void
    {
        settings()->set('timezone', 'Asia/Dhaka');
        settings()->set('working_days', 26);
        settings()->set('round_salary', true);
        settings()->set('weekend', ['Fri', 'Sat']);

        $this->freshRequest();

        $this->assertSame('Asia/Dhaka', settings('timezone'));
        $this->assertSame(26, settings('working_days'));
        $this->assertTrue(settings('round_salary'));
        $this->assertSame(['Fri', 'Sat'], settings('weekend'));
    }

    public function test_missing_settings_return_the_default(): void
    {
        $this->assertSame('UTC', settings('timezone', 'UTC'));
        $this->assertNull(settings('nothing'));
        $this->assertFalse(settings()->has('timezone'));
    }

    public function test_autoload_settings_are_read_with_one_query_and_then_cached(): void
    {
        settings()->setMany(['timezone' => 'Asia/Dhaka', 'currency' => 'BDT']);
        $this->freshRequest();

        DB::connection('tenant')->enableQueryLog();
        settings('timezone');
        settings('currency');
        $this->assertCount(1, DB::connection('tenant')->getQueryLog(), 'One query for all autoload settings.');

        // Next request: straight from the cache, no query at all.
        $this->freshRequest();
        DB::connection('tenant')->flushQueryLog();
        $this->assertSame('BDT', settings('currency'));
        $this->assertCount(0, DB::connection('tenant')->getQueryLog());
    }

    public function test_changing_a_setting_clears_the_cache(): void
    {
        settings()->set('currency', 'BDT');
        $this->assertSame('BDT', settings('currency'));

        settings()->set('currency', 'USD');
        $this->freshRequest();

        $this->assertSame('USD', settings('currency'));
    }

    public function test_non_autoload_settings_are_not_cached_with_the_rest(): void
    {
        settings()->set('report.footer', 'Confidential', autoload: false);
        $this->freshRequest();

        $this->assertArrayNotHasKey('report.footer', settings()->all());
        $this->assertSame('Confidential', settings('report.footer'));
    }

    public function test_forget_removes_a_setting(): void
    {
        settings()->set('currency', 'BDT');
        settings()->forget('currency');

        $this->assertNull(settings('currency'));
        $this->assertSame(0, Setting::count());
    }

    public function test_each_company_has_its_own_settings_and_cache(): void
    {
        settings()->set('currency', 'BDT');

        $this->tenants->switchTo($this->beta);
        $this->freshRequest();
        $this->assertNull(settings('currency'));
        settings()->set('currency', 'KES');
        $this->assertSame('KES', settings('currency'));   // builds Beta's cache entry

        $this->tenants->switchTo($this->alpha);
        $this->freshRequest();
        $this->assertSame('BDT', settings('currency'));   // builds Alpha's cache entry
        $this->assertTrue(Cache::has("tenant.{$this->alpha->id}.settings"));
        $this->assertTrue(Cache::has("tenant.{$this->beta->id}.settings"));
    }

    public function test_settings_need_a_company(): void
    {
        $this->tenants->forget();
        $this->freshRequest();

        $this->expectException(LogicException::class);
        settings('currency');
    }

    /** Like a new request: a new Settings object (the cache stays). */
    private function freshRequest(): void
    {
        app()->forgetInstance(Settings::class);
    }
}
