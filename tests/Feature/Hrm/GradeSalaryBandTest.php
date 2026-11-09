<?php

namespace Tests\Feature\Hrm;

use App\Modules\Hrm\Livewire\Organization\GradeForm;
use App\Modules\Hrm\Livewire\Organization\GradeTable;
use App\Modules\Hrm\Models\Grade;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * Task 6.7: a salary band (monthly gross from / to) per grade, for comparing offers. [REC-06]
 */
class GradeSalaryBandTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAsTenantUser();
    }

    public function test_the_band_is_saved_and_listed(): void
    {
        Livewire::test(GradeForm::class)
            ->dispatch('create-grade')
            ->set('form.name', 'G5')
            ->set('form.salary_min', '30000')
            ->set('form.salary_max', '45000')
            ->call('save')
            ->assertHasNoErrors();

        $grade = Grade::sole();
        $this->assertSame('30000.00', $grade->salary_min);
        $this->assertSame('30,000 – 45,000', $grade->salaryRange());

        Livewire::test(GradeTable::class)->assertSee('30,000 – 45,000');
    }

    public function test_the_band_is_optional_and_may_be_open_ended(): void
    {
        Livewire::test(GradeForm::class)
            ->dispatch('create-grade')
            ->set('form.name', 'G6')
            ->set('form.salary_max', '60000')   // only "to"
            ->call('save')
            ->assertHasNoErrors();

        $grade = Grade::sole();
        $this->assertNull($grade->salary_min);
        $this->assertSame('up to 60,000', $grade->salaryRange());
        $this->assertNull(Grade::factory()->create()->salaryRange());   // no band at all
    }

    public function test_to_may_not_be_below_from(): void
    {
        Livewire::test(GradeForm::class)
            ->dispatch('create-grade')
            ->set('form.name', 'G7')
            ->set('form.salary_min', '50000')
            ->set('form.salary_max', '40000')
            ->call('save')
            ->assertHasErrors(['form.salary_max']);

        $this->assertSame(0, Grade::count());
    }
}
