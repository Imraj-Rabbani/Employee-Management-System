<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TENANT tables for the payroll run (Architecture §9).
 *
 *   payroll_runs            the header: one run for one period (legacy `payroll.batch`). Status = PayrollRunStatus.
 *   payroll_run_employees   one row per employee in the run: the snapshot (days, basic, gross, net, bank, hold flags,
 *                           warnings). Also the run's SCOPE: the employees in it are the rows here.
 *   payroll_items           one row per employee × salary head (legacy `payroll`, same "long" format)
 *   payroll_adjustments     one-time earnings / deductions for a month (legacy `adjustments`), taken by the next run
 *
 * Money = DECIMAL(15,2) (MoneyCast), days = DECIMAL(6,2) (half days). Who did something = central users.id, no FK.
 *
 * Not like schema.mmd:
 *   - entity_id is on payroll_run_employees (the employee's entity when the run was made), not on every item.
 *   - No currency_id / fc_amount / exchange_rate / hold on items: one company currency (as employee_salaries);
 *     holding is per employee (is_on_hold), not per head.
 *   - Adjustments have approved_at instead of approval_status: like rosters, the status comes from the approvals (10.6).
 *   - "Only one regular run per period and scope" is checked by CreatePayrollRun (10.2): a unique index cannot say
 *     "only for regular runs", and a NULL entity (= whole company) never clashes in a MySQL unique index.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_runs', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();                     // "PR-2026-12", made by CreatePayrollRun
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->string('cycle', 20)->default('monthly');          // App\Core\Support\PeriodCycle
            $table->date('period_start');                             // copied from Period, so queries need no PHP
            $table->date('period_end');
            $table->date('pay_date')->nullable();
            $table->string('run_type', 20)->default('regular');       // PayrollRunType
            $table->foreignId('salary_head_id')->nullable()->constrained()->restrictOnDelete();   // head-wise runs only
            $table->foreignId('entity_id')->nullable()->constrained()->restrictOnDelete();        // NULL = whole company
            $table->string('status', 20)->default('draft');           // PayrollRunStatus
            $table->string('batch_id', 36)->nullable();               // Laravel job batch while processing (10.4)
            $table->decimal('total_gross', 15, 2)->default(0);
            $table->decimal('total_deductions', 15, 2)->default(0);
            $table->decimal('total_net', 15, 2)->default(0);
            $table->unsignedInteger('employee_count')->default(0);
            $table->string('note', 500)->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
            $table->timestamp('processed_at')->nullable();
            $table->timestamp('locked_at')->nullable();
            $table->unsignedBigInteger('locked_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['year', 'month', 'cycle', 'run_type']);    // duplicate-period guard, "last month's run"
            $table->index('status');
        });

        Schema::create('payroll_run_employees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();   // paid people stay in the books
            $table->foreignId('entity_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('bank_account_id')->nullable()->constrained('employee_bank_accounts')->nullOnDelete();
            $table->decimal('period_days', 6, 2)->default(0);         // calendar days of the period
            $table->decimal('payable_days', 6, 2)->default(0);
            $table->decimal('lwp_days', 6, 2)->default(0);
            $table->decimal('absent_days', 6, 2)->default(0);
            $table->decimal('basic', 15, 2)->default(0);
            $table->decimal('gross', 15, 2)->default(0);
            $table->decimal('deductions', 15, 2)->default(0);
            $table->decimal('net', 15, 2)->default(0);
            $table->boolean('is_on_hold')->default(false);            // salary held (copied from the employee, 10.12)
            $table->boolean('hold_bank_transfer')->default(false);
            $table->boolean('is_separated')->default(false);          // left in this period
            $table->string('calc_status', 20)->default('pending');    // PayrollCalcStatus
            $table->json('warnings')->nullable();                     // list of strings: "No bank account", ...
            $table->string('error', 1000)->nullable();                // why the calculation failed
            $table->timestamp('calculated_at')->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id']);       // an employee is in a run once
            $table->index(['employee_id', 'payroll_run_id']);         // "this employee's payslips"
            $table->index(['payroll_run_id', 'calc_status']);         // progress / failures
        });

        Schema::create('payroll_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('payroll_run_employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();   // copied, for YTD sums without a join
            $table->foreignId('salary_head_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2)->default(0);             // paid this period (after proration)
            $table->decimal('actual', 15, 2)->default(0);             // the full-period amount from the salary
            $table->decimal('arrear', 15, 2)->default(0);             // back pay included in amount (10.7)
            $table->string('source', 20)->default('structure');       // PayrollItemSource: which stage made it
            $table->timestamps();

            $table->index(['payroll_run_employee_id', 'salary_head_id']);
            $table->index(['payroll_run_id', 'salary_head_id']);      // register totals per head
            $table->index(['employee_id', 'salary_head_id']);         // year-to-date (tax, Phase 11)
        });

        Schema::create('payroll_adjustments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_head_id')->constrained()->restrictOnDelete();   // earning or deduction: the head says
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('month');
            $table->decimal('amount', 15, 2);                         // always ≥ 0, the head's type gives the sign
            $table->string('reason', 500);
            // Set when a run takes it. A deleted (draft) run frees it again.
            $table->foreignId('payroll_run_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('requested_by_user_id')->nullable()->index();
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['year', 'month', 'employee_id']);          // "adjustments for this run"
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_adjustments');
        Schema::dropIfExists('payroll_items');
        Schema::dropIfExists('payroll_run_employees');
        Schema::dropIfExists('payroll_runs');
    }
};
