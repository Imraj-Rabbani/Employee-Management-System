<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The application pipeline's history (6.5). [REC-04]
 *
 *   application_stage_changes  one row per move: from → to, who, when, note, and the reason for a
 *                              rejection / withdrawal. The first row of every application has
 *                              from_stage NULL → "applied" (written by ApplyToPosting).
 *   applications.rejection_reason_id  the CURRENT reason (copied from the last move), so reports (6.9)
 *                              can count "why do we lose candidates" without reading the history.
 *   applications.stage_changed_at     when it reached its current stage (time in stage, time-to-hire).
 *
 * Existing applications get their first history row here (backfill), so every application has one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('application_stage_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->cascadeOnDelete();
            $table->string('from_stage', 20)->nullable();
            $table->string('to_stage', 20);
            $table->foreignId('rejection_reason_id')->nullable()->constrained()->restrictOnDelete();
            $table->text('note')->nullable();
            $table->unsignedBigInteger('changed_by_user_id')->nullable()->index();   // central users.id
            $table->timestamp('created_at')->nullable();   // history rows are never edited: no updated_at

            $table->index(['application_id', 'id']);
        });

        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('rejection_reason_id')->nullable()->after('stage')->constrained()->restrictOnDelete();
            $table->timestamp('stage_changed_at')->nullable()->after('rejection_reason_id');
        });

        // Backfill: every application so far starts its history as "applied".
        DB::table('applications')->update(['stage_changed_at' => DB::raw('created_at')]);
        DB::table('application_stage_changes')->insertUsing(
            ['application_id', 'from_stage', 'to_stage', 'changed_by_user_id', 'created_at'],
            DB::table('applications')->select('id', DB::raw('NULL'), 'stage', 'created_by_user_id', 'created_at'),
        );
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('rejection_reason_id');
            $table->dropColumn('stage_changed_at');
        });
        Schema::dropIfExists('application_stage_changes');
    }
};
