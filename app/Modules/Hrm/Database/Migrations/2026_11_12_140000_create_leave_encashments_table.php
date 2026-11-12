<?php

use App\Modules\Hrm\Models\LeaveEncashment;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task 8.6: leave encashments (legacy `leaveencash`): "pay me 5 unused days of Earned leave". [LV-06]
 * Approval type "leave_encashment". On approval the days leave the balance (ledger Encashed, source = this).
 *
 * The MONEY is decided by payroll: there are no salaries yet (Phase 9). An approved, not withdrawn
 * encashment with payroll_run_id NULL is "ready for payroll" (LeaveEncashment::readyForPayroll()); the payroll
 * run (Phase 10) works out `amount` (e.g. basic ÷ 30 × days), pays it and stamps payroll_run_id / paid_at.
 * payroll_run_id is a plain id until payroll_runs exists (FK added in Phase 10).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_encashments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('leave_year');           // the balance the days come from
            $table->decimal('days', 6, 2);
            $table->string('reason', 500)->nullable();
            $table->decimal('amount', 14, 2)->nullable();          // set by payroll (Phase 10)
            $table->unsignedBigInteger('payroll_run_id')->nullable()->index();
            $table->timestamp('paid_at')->nullable();
            $table->unsignedBigInteger('requested_by_user_id')->nullable();
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('rejected_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->unsignedBigInteger('withdrawn_by_user_id')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'leave_type_id']);
        });

        if (! DB::table('approval_types')->where('key', 'leave_encashment')->exists()) {
            DB::table('approval_types')->insert([
                'key' => 'leave_encashment',
                'name' => 'Leave encashment',
                'model_class' => LeaveEncashment::class,
                'is_chained' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('approval_types')->where('key', 'leave_encashment')->delete();
        Schema::dropIfExists('leave_encashments');
    }
};
