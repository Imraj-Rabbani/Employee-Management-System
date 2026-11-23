<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 10.7: arrears (back pay for an EARLIER period, paid in a later run).
 *
 *   payroll_arrears   the ledger: one row per run × employee × earlier period × head. "What was already paid for
 *                     October" = October's own items + the arrears for October in any other run, so a later run
 *                     only pays what is still missing and nothing is paid twice.
 *   payroll_run_employees.joiner_deferred   joined after the pay scale's threshold day: nothing paid in that run,
 *                     the next run pays the period as arrears.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payroll_arrears', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();              // the run that pays it
            $table->foreignId('payroll_run_employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('salary_head_id')->constrained()->restrictOnDelete();
            $table->foreignId('for_payroll_run_id')->constrained('payroll_runs')->restrictOnDelete();   // the earlier run it corrects
            $table->foreignId('for_payroll_run_employee_id')->constrained('payroll_run_employees')->restrictOnDelete();
            $table->date('for_period_start');                                                   // copied, for reports
            $table->date('for_period_end');
            $table->string('reason', 20);                                                       // ArrearReason
            $table->decimal('due', 15, 2);                                                      // what the period should pay now
            $table->decimal('paid_before', 15, 2);                                              // what was paid for it so far
            $table->decimal('amount', 15, 2);                                                   // due − paid_before (negative = recovery)
            $table->timestamps();

            $table->index(['employee_id', 'for_payroll_run_employee_id']);                      // "already paid for that period"
            $table->index('payroll_run_employee_id');
        });

        Schema::table('payroll_run_employees', function (Blueprint $table) {
            $table->boolean('joiner_deferred')->default(false)->after('is_separated');
        });
    }

    public function down(): void
    {
        Schema::table('payroll_run_employees', fn (Blueprint $table) => $table->dropColumn('joiner_deferred'));
        Schema::dropIfExists('payroll_arrears');
    }
};
