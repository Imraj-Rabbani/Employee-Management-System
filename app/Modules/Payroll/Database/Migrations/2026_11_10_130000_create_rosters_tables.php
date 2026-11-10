<?php

use App\Modules\Payroll\Models\Roster;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * TENANT tables for rosters: the PLANNED shift per employee per day. [TA-03]
 *
 *   rosters        one plan for one org unit and a date range, e.g. "Factory, 1–31 December". Approvable.
 *   roster_items   one cell of the grid: employee × date → shift, day off, or on call
 *
 * Only APPROVED rosters count for attendance (7.4); without one, the employee's own / default shift is used.
 * No status column (same as manpower requests): draft / pending / approved comes from the approval rows,
 * and onApproved() stamps approved_at so lists and the attendance engine need not read the approvals.
 * Also registers the approval type "roster" (see Workflow → Setup).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('rosters', function (Blueprint $table) {
            $table->id();
            $table->foreignId('org_unit_id')->constrained()->restrictOnDelete();
            $table->string('title', 150);
            $table->date('from_date');
            $table->date('to_date');
            $table->text('note')->nullable();
            $table->unsignedBigInteger('requested_by_user_id')->nullable()->index();   // central users.id
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();

            $table->index(['org_unit_id', 'from_date']);
        });

        Schema::create('roster_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('roster_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->date('date');
            // The planned shift. NULL together with is_off = a day off; NULL otherwise = not planned.
            $table->foreignId('shift_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_off')->default(false);
            $table->boolean('is_on_call')->default(false);
            // Different hours for this one day only (rare). NULL = the shift's own times.
            $table->time('start_time')->nullable();
            $table->time('end_time')->nullable();
            $table->timestamps();

            $table->unique(['roster_id', 'employee_id', 'date']);   // one cell per employee and day
            $table->index(['employee_id', 'date']);                 // attendance (7.4): "the plan for X on day Y"
        });

        if (! DB::table('approval_types')->where('key', 'roster')->exists()) {
            DB::table('approval_types')->insert([
                'key' => 'roster',
                'name' => 'Roster',
                'model_class' => Roster::class,
                'is_chained' => true,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        DB::table('approval_types')->where('key', 'roster')->delete();
        Schema::dropIfExists('roster_items');
        Schema::dropIfExists('rosters');
    }
};
