<?php

use App\Modules\Payroll\Models\PayrollAdjustment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 10.6: overtime and adjustments in the payroll run.
 *
 *   payroll_adjustments      go through the workflow now (approval type "payroll_adjustment"); like leave
 *                            encashments the decision is stamped: rejected_at, withdrawn_at (+ who)
 *   payroll_run_employees    overtime_hours = the approved overtime hours the run paid (for the register)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_adjustments', function (Blueprint $table) {
            $table->timestamp('rejected_at')->nullable()->after('approved_at');
            $table->timestamp('withdrawn_at')->nullable()->after('rejected_at');
            $table->unsignedBigInteger('withdrawn_by_user_id')->nullable()->after('withdrawn_at');   // central users: no FK
        });

        Schema::table('payroll_run_employees', function (Blueprint $table) {
            $table->decimal('overtime_hours', 6, 2)->default(0)->after('absent_days');
        });

        if (! DB::table('approval_types')->where('key', 'payroll_adjustment')->exists()) {
            DB::table('approval_types')->insert([
                'key' => 'payroll_adjustment',
                'name' => 'Payroll adjustment',
                'model_class' => PayrollAdjustment::class,
                'is_chained' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('approval_types')->where('key', 'payroll_adjustment')->delete();
        Schema::table('payroll_run_employees', fn (Blueprint $table) => $table->dropColumn('overtime_hours'));
        Schema::table('payroll_adjustments', fn (Blueprint $table) => $table->dropColumn(['rejected_at', 'withdrawn_at', 'withdrawn_by_user_id']));
    }
};
