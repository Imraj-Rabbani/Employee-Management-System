<?php

namespace Tests\Feature\Payroll;

use App\Core\Support\Period;
use App\Models\User;
use App\Modules\Hrm\Models\Employee;
use App\Modules\Payroll\Actions\CreatePayrollRun;
use App\Modules\Payroll\Actions\ProcessPayrollRun;
use App\Modules\Payroll\Data\NewPayrollRun;
use App\Modules\Payroll\Enums\PayrollCalcStatus;
use App\Modules\Payroll\Enums\PayrollRunStatus;
use App\Modules\Payroll\Enums\PayrollRunType;
use App\Modules\Payroll\Jobs\CalculateEmployeePayrollJob;
use App\Modules\Payroll\Jobs\FinishPayrollRun;
use App\Modules\Payroll\Livewire\PayrollRunProgress;
use App\Modules\Payroll\Livewire\PayrollRunTable;
use App\Modules\Payroll\Models\EmployeeSalary;
use App\Modules\Payroll\Models\PayrollItem;
use App\Modules\Payroll\Models\PayrollRun;
use App\Modules\Payroll\Models\SalaryHead;
use Carbon\CarbonImmutable;
use Exception;
use Illuminate\Bus\Batch;
use Illuminate\Bus\BatchRepository;
use Illuminate\Bus\PendingBatch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use InvalidArgumentException;
use Livewire\Livewire;
use Tests\Concerns\ActsAsTenantUser;
use Tests\TestCase;

/**
 * 10.4: ProcessPayrollRun (one queued job per employee in a job batch), the batch's finish step, and the list's
 * Calculate button + progress modal. The test queue is "sync": a batch runs completely inside dispatch().
 */
class ProcessPayrollRunTest extends TestCase
{
    use ActsAsTenantUser;
    use RefreshDatabase;

    private User $admin;

    private SalaryHead $basic;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = $this->actingAsTenantUser();
        $this->basic = SalaryHead::factory()->create(['is_basic' => true, 'amount' => null, 'sequence' => 1]);
    }

    private function employee(string $code, bool $withSalary = true): Employee
    {
        $employee = Employee::factory()->create(['code' => $code, 'join_date' => '2025-01-01']);
        if ($withSalary) {
            $this->giveSalary($employee);
        }

        return $employee;
    }

    private function giveSalary(Employee $employee, string $basic = '30000'): void
    {
        EmployeeSalary::create(['employee_id' => $employee->id, 'salary_head_id' => $this->basic->id, 'amount' => $basic, 'effective_from' => '2025-01-01']);
    }

    private function makeRun(PayrollRunType $type = PayrollRunType::Regular): PayrollRun
    {
        return app(CreatePayrollRun::class)->handle(new NewPayrollRun(Period::of(2026, 12), $type), $this->admin);
    }

    public function test_the_batch_calculates_everyone_and_the_run_ends_processed(): void
    {
        $this->employee('A1');
        $this->employee('A2');
        $this->employee('A3', withSalary: false);   // fails: no salary
        $run = $this->makeRun();

        $run = app(ProcessPayrollRun::class)->handle($run)->fresh();

        $this->assertSame(PayrollRunStatus::Processed, $run->status);
        $this->assertNotNull($run->processed_at);
        $this->assertSame(3, $run->employee_count);
        $this->assertSame('60000.00', $run->total_gross->toDecimal());
        $this->assertSame('60000.00', $run->total_net->toDecimal());
        $this->assertSame(2, PayrollItem::count());

        $failed = $run->employees()->where('calc_status', PayrollCalcStatus::Failed->value)->sole();
        $this->assertSame('A3', $failed->employee->code);
        $this->assertStringContainsString('No salary on', $failed->error);
    }

    public function test_calculating_again_after_a_fix_gives_one_set_of_items(): void
    {
        $this->employee('A1');
        $fixme = $this->employee('A2', withSalary: false);
        $run = $this->makeRun();
        app(ProcessPayrollRun::class)->handle($run);

        $this->giveSalary($fixme, '20000');
        $run = app(ProcessPayrollRun::class)->handle($run->fresh())->fresh();

        $this->assertSame(PayrollRunStatus::Processed, $run->status);
        $this->assertSame(0, $run->employees()->where('calc_status', PayrollCalcStatus::Failed->value)->count());
        $this->assertSame(2, PayrollItem::count());
        $this->assertSame('50000.00', $run->total_gross->toDecimal());
    }

    public function test_it_queues_one_job_per_employee_and_leaves_the_run_processing(): void
    {
        Bus::fake();
        $this->employee('A1');
        $this->employee('A2');
        $run = $this->makeRun();
        $run->employees()->update(['calc_status' => PayrollCalcStatus::Failed->value, 'error' => 'old']);

        app(ProcessPayrollRun::class)->handle($run);

        Bus::assertBatched(fn (PendingBatch $batch) => $batch->jobs->count() === 2
            && $batch->allowsFailures()
            && $batch->jobs->every(fn ($job) => $job instanceof CalculateEmployeePayrollJob && $job->tenantId !== null));
        $run = $run->fresh();
        $this->assertSame(PayrollRunStatus::Processing, $run->status);
        $this->assertSame(2, $run->employees()->where('calc_status', PayrollCalcStatus::Pending->value)->whereNull('error')->count());

        // Just started: it is running, a second click is refused.
        $this->assertSame('This run is being calculated right now.', app(ProcessPayrollRun::class)->problem($run));

        // Nothing happened for over half an hour (queue emptied, no worker): stuck, so it may be started again.
        PayrollRun::whereKey($run->id)->update(['updated_at' => now()->subHour()]);
        $this->assertNull(app(ProcessPayrollRun::class)->problem($run->fresh()));
        app(ProcessPayrollRun::class)->handle($run->fresh());
        $this->assertSame(PayrollRunStatus::Processing, $run->fresh()->status);
        $this->assertSame('This run is being calculated right now.', app(ProcessPayrollRun::class)->problem($run->fresh()));
    }

    public function test_the_finish_step_totals_up_and_only_finishes_its_own_run(): void
    {
        $this->employee('A1');
        $run = $this->makeRun();
        $batch = fn (string $id) => new Batch(app('queue'), app(BatchRepository::class), $id, 'x', 0, 0, 0, [], [], CarbonImmutable::now());

        // A draft run is left alone.
        (new FinishPayrollRun($run->id))($batch('b1'));
        $this->assertSame(PayrollRunStatus::Draft, $run->fresh()->status);

        // Processing with another batch (it was started again): the old batch must not finish it.
        $run->forceFill(['status' => PayrollRunStatus::Processing, 'batch_id' => 'b2'])->save();
        (new FinishPayrollRun($run->id))($batch('b1'));
        $this->assertSame(PayrollRunStatus::Processing, $run->fresh()->status);

        // Its own batch: totals added up, Processed.
        (new FinishPayrollRun($run->id))($batch('b2'));
        $run = $run->fresh();
        $this->assertSame(PayrollRunStatus::Processed, $run->status);
        $this->assertNotNull($run->processed_at);
        $this->assertSame(1, $run->employee_count);
    }

    public function test_which_runs_may_be_calculated(): void
    {
        $action = app(ProcessPayrollRun::class);
        $this->employee('A1');

        $run = $this->makeRun(PayrollRunType::OffCycle);   // an upload run (10.11)
        $this->assertSame('Upload the amounts first.', $action->problem($run));
        $settlement = PayrollRun::factory()->forPeriod(Period::of(2027, 2))->create(['run_type' => PayrollRunType::FinalSettlement]);
        $this->assertSame('Final settlement runs cannot be calculated yet.', $action->problem($settlement));

        $run = $this->makeRun();
        $run->forceFill(['status' => PayrollRunStatus::Pending])->save();
        $this->assertSame('This run is waiting for approval, so it cannot be calculated again.', $action->problem($run));

        $empty = PayrollRun::factory()->forPeriod(Period::of(2027, 1))->create();
        $this->assertSame('This run has no employees.', $action->problem($empty));

        $this->expectException(InvalidArgumentException::class);
        $action->handle($empty);
    }

    public function test_a_crashed_job_marks_the_employee_failed(): void
    {
        $employee = $this->employee('A1');
        $run = $this->makeRun();
        $row = $run->employees()->sole();
        $row->items()->create(['payroll_run_id' => $run->id, 'employee_id' => $employee->id, 'salary_head_id' => $this->basic->id, 'amount' => '100']);

        (new CalculateEmployeePayrollJob($row->id))->failed(new Exception('Lost connection'));

        $row = $row->fresh();
        $this->assertSame(PayrollCalcStatus::Failed, $row->calc_status);
        $this->assertSame('The calculation stopped unexpectedly: Lost connection', $row->error);
        $this->assertSame(0, PayrollItem::count());
    }

    public function test_the_list_calculates_the_ticked_runs_and_the_progress_shows_failures(): void
    {
        $this->employee('A1');
        $bad = $this->employee('A2', withSalary: false);
        $run = $this->makeRun();

        Livewire::test(PayrollRunTable::class)
            ->set('selected', [(string) $run->id])
            ->call('calculate')
            ->assertDispatched('show-payroll-run', id: $run->id)
            ->assertDispatched('toast');
        $this->assertSame(PayrollRunStatus::Processed, $run->fresh()->status);

        Livewire::test(PayrollRunProgress::class)
            ->dispatch('show-payroll-run', id: $run->id)
            ->assertSee('2 of 2 employees')
            ->assertSee('100%')
            ->assertSee('1 failed')
            ->assertSee($bad->code)
            ->assertSee('No salary on')
            ->assertDontSeeHtml('wire:poll');
    }

    public function test_the_progress_polls_only_while_processing(): void
    {
        Bus::fake();
        $this->employee('A1');
        $run = $this->makeRun();
        app(ProcessPayrollRun::class)->handle($run);

        Livewire::test(PayrollRunProgress::class)
            ->dispatch('show-payroll-run', id: $run->id)
            ->assertSee('0 of 1 employees')
            ->assertSeeHtml('wire:poll.2s')
            ->assertSee('queue:work');

        Livewire::test(PayrollRunTable::class)->assertSeeHtml('wire:poll.3s')->assertSee('0 / 1');
    }

    public function test_a_user_without_the_process_permission_cannot_calculate(): void
    {
        $this->employee('A1');
        $run = $this->makeRun(); // as admin
        $this->actingAsTenantUser(tenant: $this->admin->tenant, permissions: ['payroll.run.view']);

        Livewire::test(PayrollRunTable::class)->set('selected', [(string) $run->id])->call('calculate')->assertForbidden();
    }
}
