<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 10.11: head-wise and off-cycle runs (PR-07).
 *
 *   payroll_runs.amount_basis    head-wise: where each amount comes from (AmountBasis: head_rule / salary / upload);
 *                                off-cycle: always upload; regular: NULL
 *   payroll_runs eligibility     who a head-wise run pays (legacy `disbursements`): religions (JSON list, NULL =
 *                                any), confirmed_only, joined_before (joined BEFORE this date), prorate_by_service
 *                                (joined this year → days from joining to year end ÷ days in the year)
 *   payroll_run_amounts          the uploaded amounts of an upload run: one row per employee and head. The input;
 *                                the calculation turns them into payroll items (source Upload).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->string('amount_basis', 20)->nullable()->after('salary_head_id');
            $table->json('religions')->nullable()->after('amount_basis');
            $table->boolean('confirmed_only')->default(false)->after('religions');
            $table->date('joined_before')->nullable()->after('confirmed_only');
            $table->boolean('prorate_by_service')->default(false)->after('joined_before');
        });

        Schema::create('payroll_run_amounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('payroll_run_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->foreignId('salary_head_id')->constrained()->restrictOnDelete();
            $table->decimal('amount', 15, 2);                 // > 0; the head's type makes it an earning or a deduction
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['payroll_run_id', 'employee_id', 'salary_head_id'], 'payroll_run_amounts_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payroll_run_amounts');
        Schema::table('payroll_runs', fn (Blueprint $table) => $table->dropColumn(['amount_basis', 'religions', 'confirmed_only', 'joined_before', 'prorate_by_service']));
    }
};
