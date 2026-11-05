<?php

namespace Tests\Feature\Core;

use App\Modules\Core\Livewire\FiscalYearForm;
use App\Modules\Core\Livewire\FiscalYearsTable;
use App\Modules\Core\Models\FiscalYear;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 2.8d: Setup → Fiscal Years. [FND-04]
 */
class FiscalYearsPageTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $tenant = $this->createMigratedTenant();
        $this->actingAsTenantUser(tenant: $tenant);
    }

    public function test_the_page_opens_from_the_setup_menu(): void
    {
        $this->year('FY 2025-26', '2025-07-01', '2026-06-30');

        $this->get('/dashboard')->assertSee(route('core.fiscal-years'));
        $this->get('/core/fiscal-years')
            ->assertOk()
            ->assertSee('New fiscal year')
            ->assertSee('FY 2025-26')
            ->assertSeeLivewire(FiscalYearForm::class);
    }

    public function test_the_list_shows_the_newest_year_first(): void
    {
        $this->year('FY 2024-25', '2024-07-01', '2025-06-30');
        $this->year('FY 2025-26', '2025-07-01', '2026-06-30');

        Livewire::test(FiscalYearsTable::class)->assertSeeInOrder(['FY 2025-26', 'FY 2024-25']);
    }

    public function test_new_suggests_the_year_after_the_latest(): void
    {
        $this->year('FY 2025-26', '2025-07-01', '2026-06-30');

        Livewire::test(FiscalYearForm::class)
            ->dispatch('create-fiscal-year')
            ->assertSet('yearId', null)
            ->assertSet('startDate', '2026-07-01')
            ->assertSet('endDate', '2027-06-30')
            ->assertSet('name', 'FY 2026-27');
    }

    public function test_the_first_year_is_created_and_made_active(): void
    {
        Carbon::setTestNow('2026-01-10');

        Livewire::test(FiscalYearForm::class)
            ->dispatch('create-fiscal-year')
            ->assertSet('startDate', '2026-01-01')
            ->assertSet('endDate', '2026-12-31')
            ->assertSet('name', 'FY 2026')
            ->call('save')
            ->assertHasNoErrors()
            ->assertDispatched('fiscal-year-saved');

        $this->assertSame('FY 2026', FiscalYear::active()?->name);
    }

    public function test_a_later_year_does_not_take_over_the_active_one(): void
    {
        $this->year('FY 2025-26', '2025-07-01', '2026-06-30')->activate();

        Livewire::test(FiscalYearForm::class)->dispatch('create-fiscal-year')->call('save');

        $this->assertSame(2, FiscalYear::count());
        $this->assertSame('FY 2025-26', FiscalYear::active()?->name);
    }

    public function test_overlapping_dates_are_refused(): void
    {
        $this->year('FY 2025-26', '2025-07-01', '2026-06-30');

        Livewire::test(FiscalYearForm::class)
            ->dispatch('create-fiscal-year')
            ->set('name', 'Clash')
            ->set('startDate', '2026-01-01')
            ->set('endDate', '2026-12-31')
            ->call('save')
            ->assertHasErrors(['startDate']);

        $this->assertSame(1, FiscalYear::count());
    }

    public function test_it_validates_the_fields(): void
    {
        $this->year('FY 2025-26', '2025-07-01', '2026-06-30');

        Livewire::test(FiscalYearForm::class)
            ->dispatch('create-fiscal-year')
            ->set('name', 'FY 2025-26')
            ->set('startDate', '2030-12-31')
            ->set('endDate', '2030-01-01')
            ->call('save')
            ->assertHasErrors(['name' => 'unique', 'endDate' => 'after']);
    }

    public function test_editing_a_year_keeps_its_own_dates_from_counting_as_an_overlap(): void
    {
        $year = $this->year('FY 2025-26', '2025-07-01', '2026-06-30');

        Livewire::test(FiscalYearForm::class)
            ->dispatch('edit-fiscal-year', id: $year->id)
            ->assertSet('name', 'FY 2025-26')
            ->set('name', 'Fiscal 2025-26')
            ->set('endDate', '2026-06-29')
            ->call('save')
            ->assertHasNoErrors();

        $year->refresh();
        $this->assertSame('Fiscal 2025-26', $year->name);
        $this->assertSame('2026-06-29', $year->end_date->toDateString());
    }

    public function test_make_active_switches_the_active_year(): void
    {
        $old = $this->year('FY 2024-25', '2024-07-01', '2025-06-30');
        $new = $this->year('FY 2025-26', '2025-07-01', '2026-06-30');
        $old->activate();

        Livewire::test(FiscalYearsTable::class)
            ->assertSee('Make active')
            ->call('activate', $new->id)
            ->assertDispatched('toast');

        $this->assertSame($new->id, FiscalYear::active()?->id);
    }

    private function year(string $name, string $start, string $end): FiscalYear
    {
        return FiscalYear::create(['name' => $name, 'start_date' => $start, 'end_date' => $end]);
    }
}
