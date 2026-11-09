<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Interviews of an application, in rounds, with a panel of employees who each score every factor (6.6). [REC-05]
 *
 *   interviews            one round of one application: when, where, status (InterviewStatus).
 *                         round 1, 2, 3 ... is unique per application.
 *   interview_panelists   the employees on that round's panel. Their own recommendation + comment, and
 *                         submitted_at once they have sent their score sheet (then it is locked).
 *   interview_scores      one score per panelist per interview factor (1–5, see InterviewScoring::MAX).
 *                         `weight` is the factor's weight AT THE TIME OF SCORING: changing the setup later
 *                         does not change old results.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('application_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('round');
            $table->dateTime('scheduled_at');
            $table->unsignedSmallInteger('duration_minutes')->default(30);
            $table->string('location', 300)->nullable();   // a room, an address or a video-call link
            $table->string('status', 20)->default('scheduled');
            $table->text('notes')->nullable();               // for the panel, e.g. "focus on Excel skills"
            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['application_id', 'round']);
            $table->index('scheduled_at');
        });

        Schema::create('interview_panelists', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_id')->constrained()->cascadeOnDelete();
            $table->foreignId('employee_id')->constrained()->restrictOnDelete();
            $table->string('recommendation', 20)->nullable();   // InterviewRecommendation (6.6c)
            $table->text('comment')->nullable();
            $table->timestamp('submitted_at')->nullable();      // score sheet sent: locked
            $table->timestamps();

            $table->unique(['interview_id', 'employee_id']);
        });

        Schema::create('interview_scores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('interview_panelist_id')->constrained()->cascadeOnDelete();
            $table->foreignId('interview_factor_id')->constrained()->restrictOnDelete();
            $table->unsignedTinyInteger('score');
            $table->decimal('weight', 5, 2);                    // copied from interview_factors.weight
            $table->string('note', 500)->nullable();
            $table->timestamps();

            // Own short name: the automatic one is longer than MySQL's 64-character limit.
            $table->unique(['interview_panelist_id', 'interview_factor_id'], 'interview_scores_panelist_factor_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('interview_scores');
        Schema::dropIfExists('interview_panelists');
        Schema::dropIfExists('interviews');
    }
};
