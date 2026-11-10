<?php

namespace Tests\Feature\Hrm;

use App\Core\Tenancy\TenantManager;
use App\Modules\Hrm\Models\InterviewFactor;
use App\Modules\Hrm\Models\RejectionReason;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantDatabases;
use Tests\TestCase;

/**
 * Task 6.1: recruitment setup tables (interview factors, rejection reasons). [REC-04, REC-05]
 */
class RecruitmentSetupModelsTest extends TestCase
{
    use CreatesTenantDatabases;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        app(TenantManager::class)->switchTo($this->createMigratedTenant());
    }

    public function test_both_models_live_in_the_tenant_database(): void
    {
        foreach ([InterviewFactor::class, RejectionReason::class] as $model) {
            $row = $model::factory()->create();
            $this->assertSame('tenant', $row->getConnectionName(), $model);
            $this->assertTrue($row->fresh()->is_active, $model.' should be active by default');
        }
    }

    public function test_interview_factor_defaults_and_casts(): void
    {
        $factor = InterviewFactor::create(['name' => 'Communication'])->fresh();

        $this->assertSame('0.00', $factor->weight);
        $this->assertFalse($factor->is_required);
        $this->assertSame(0, $factor->sequence);
    }

    public function test_ordered_sorts_by_sequence_then_name(): void
    {
        InterviewFactor::factory()->create(['name' => 'Attitude', 'sequence' => 2]);
        InterviewFactor::factory()->create(['name' => 'Technical skill', 'sequence' => 1]);
        InterviewFactor::factory()->create(['name' => 'Communication', 'sequence' => 2]);

        $this->assertSame(['Technical skill', 'Attitude', 'Communication'], InterviewFactor::ordered()->pluck('name')->all());
    }

    public function test_names_are_unique(): void
    {
        RejectionReason::create(['name' => 'Did not attend']);

        $this->expectException(QueryException::class);
        RejectionReason::create(['name' => 'Did not attend']);
    }
}
