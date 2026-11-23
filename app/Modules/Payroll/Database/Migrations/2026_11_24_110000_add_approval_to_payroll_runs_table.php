<?php

use App\Modules\Payroll\Models\PayrollRun;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * 10.10: submit → approve (workflow, approval type "payroll_run") → lock. The status column says where the run
 * is; these stamp who / when. locked_at / locked_by_user_id exist since 10.1.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payroll_runs', function (Blueprint $table) {
            $table->timestamp('submitted_at')->nullable()->after('processed_at');
            $table->unsignedBigInteger('submitted_by_user_id')->nullable()->after('submitted_at');   // central users: no FK
            $table->timestamp('approved_at')->nullable()->after('submitted_by_user_id');
        });

        if (! DB::table('approval_types')->where('key', 'payroll_run')->exists()) {
            DB::table('approval_types')->insert([
                'key' => 'payroll_run',
                'name' => 'Payroll run',
                'model_class' => PayrollRun::class,
                'is_chained' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('approval_types')->where('key', 'payroll_run')->delete();
        Schema::table('payroll_runs', fn (Blueprint $table) => $table->dropColumn(['submitted_at', 'submitted_by_user_id', 'approved_at']));
    }
};
