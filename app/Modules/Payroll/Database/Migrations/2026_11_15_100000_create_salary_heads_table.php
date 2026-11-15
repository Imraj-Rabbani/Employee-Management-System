<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Salary heads = the pay components (Basic, House rent, PF, Tax ...), legacy `salaryheads`. [PC-01]
 * Money is DECIMAL(15,2), rates DECIMAL(9,4), percentages DECIMAL(5,2) (Architecture §9.3).
 * Which column is used depends on calc_method: amount (fixed / per day), rate (% of basic / gross), formula.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_heads', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();          // short name used in formulas, e.g. BASIC
            $table->string('name', 100)->unique();
            $table->string('type', 20);                    // HeadType
            $table->string('calc_method', 20);             // CalcMethod
            $table->decimal('amount', 15, 2)->nullable();
            $table->decimal('rate', 9, 4)->nullable();
            $table->string('formula', 500)->nullable();
            $table->boolean('in_gross')->default(true);    // counted in gross salary
            $table->decimal('taxable_percent', 5, 2)->default(100);
            $table->decimal('tax_exemption_cap', 15, 2)->nullable();
            $table->boolean('prorate')->default(true);     // reduced for joiners / leavers
            $table->boolean('arrear_eligible')->default(true);
            $table->string('pay_cycle', 20)->default('monthly');   // PayCycle
            $table->unsignedInteger('sequence')->default(0);       // order on the payslip and in calculation
            $table->string('gl_account', 50)->nullable();
            $table->unsignedSmallInteger('eligible_after_months')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('sequence');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_heads');
    }
};
