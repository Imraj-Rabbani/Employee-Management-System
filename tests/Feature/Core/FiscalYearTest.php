<?php

namespace Tests\Feature\Core;

use App\Core\Tenancy\TenantManager;
use App\Models\Tenant;
use App\Modules\Core\Models\FiscalYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\Concerns\CreatesTenantDatabases;
use Tests\TestCase;

/**
 * Task 2.8c: fiscal years, one active. [FND-04]
 */
class FiscalYearTest extends TestCase
{
    use CreatesTenantDatabases;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = $this->createMigratedTenant();
        app(TenantManager::class)->switchTo($this->tenant);
    }

    public function test_a_new_year_is_not_active(): void
    {
        $year = $this->year('FY 2025-26', '2025-07-01', '2026-06-30');

        $this->assertFalse($year->is_active);
        $this->assertNull(FiscalYear::active());
    }

    public function test_activating_a_year_switches_the_others_off(): void
    {
        $old = $this->year('FY 2024-25', '2024-07-01', '2025-06-30');
        $new = $this->year('FY 2025-26', '2025-07-01', '2026-06-30');

        $old->activate();
        $this->assertSame($old->id, FiscalYear::active()?->id);

        $new->activate();
        $this->assertSame($new->id, FiscalYear::active()?->id);
        $this->assertFalse($old->fresh()?->is_active);
        $this->assertSame(1, FiscalYear::where('is_active', true)->count());
    }

    public function test_the_tenant_knows_its_active_year(): void
    {
        $this->year('FY 2025-26', '2025-07-01', '2026-06-30')->activate();

        $this->assertSame('FY 2025-26', tenant()?->activeFiscalYear()?->name);
    }

    public function test_only_the_current_tenant_can_be_asked(): void
    {
        $other = Tenant::factory()->create();

        $this->expectException(LogicException::class);
        $other->activeFiscalYear();
    }

    public function test_it_finds_the_year_a_date_falls_in(): void
    {
        $year = $this->year('FY 2025-26', '2025-07-01', '2026-06-30');

        $this->assertSame($year->id, FiscalYear::containing('2025-07-01')?->id);   // first day
        $this->assertSame($year->id, FiscalYear::containing('2026-06-30')?->id);   // last day
        $this->assertNull(FiscalYear::containing('2026-07-01'));
        $this->assertTrue($year->contains('2026-01-15'));
        $this->assertFalse($year->contains('2025-06-30'));
    }

    public function test_overlapping_finds_clashing_years(): void
    {
        $this->year('FY 2025-26', '2025-07-01', '2026-06-30');

        $this->assertSame(1, FiscalYear::overlapping('2026-06-30', '2027-06-29')->count());   // touches the last day
        $this->assertSame(1, FiscalYear::overlapping('2025-01-01', '2025-12-31')->count());
        $this->assertSame(0, FiscalYear::overlapping('2026-07-01', '2027-06-30')->count());   // the next year: fine
    }

    private function year(string $name, string $start, string $end): FiscalYear
    {
        return FiscalYear::create(['name' => $name, 'start_date' => $start, 'end_date' => $end]);
    }
}
