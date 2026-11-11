<?php

use App\Modules\Payroll\Models\OvertimeRequest;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Task 7.7: overtime requests (legacy `overtimes`). [TA-07]
 * hours = asked; recommended_hours = the last number before the final level; approved_hours = the final level's.
 * The approvers' numbers are entered in the approval modal (approvals.amount); onApproved() copies them here.
 * Approved hours go to attendance_days.ot_approved_minutes, which payroll pays at the day type's rate.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('overtime_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->date('date');
            $table->foreignId('shift_id')->nullable()->constrained()->nullOnDelete();   // the day's shift when asked (information)
            $table->decimal('hours', 5, 2);
            $table->decimal('recommended_hours', 5, 2)->nullable();
            $table->decimal('approved_hours', 5, 2)->nullable();
            $table->string('detail', 500)->nullable();
            $table->unsignedBigInteger('requested_by_user_id')->nullable();   // central users table: no FK across databases
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'date']);
        });

        if (! DB::table('approval_types')->where('key', 'overtime')->exists()) {
            DB::table('approval_types')->insert([
                'key' => 'overtime',
                'name' => 'Overtime',
                'model_class' => OvertimeRequest::class,
                'is_chained' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('approval_types')->where('key', 'overtime')->delete();
        Schema::dropIfExists('overtime_requests');
    }
};
