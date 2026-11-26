<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Interview questions, question sets and stages (the user's design, 2026-11-27).
 *
 *   interview_questions    was interview_factors: one company-wide list of questions, each scored 1–5.
 *                          The weight and "required" move to the set (every question of a set must be scored).
 *   question_sets          a named group of questions, e.g. "Technical – Software".
 *   question_set_items     the questions of a set, each with its weight in THAT set (the set's weights add up to 100).
 *   interview_stages       a stage of ONE job posting, e.g. "Stage 1", with the question set its interviews use.
 *                          Name unique per posting. Every interview belongs to a stage; an application is in a
 *                          stage at most once (cancelled interviews do not count).
 *   interview_scores       interview_factor_id → interview_question_id.
 *
 * Existing data: the factors become questions; one set "General" holds the active ones with their weights;
 * every posting's interview rounds become stages "Round 1", "Round 2" ... using "General", so old scores stay.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::rename('interview_factors', 'interview_questions');

        Schema::table('interview_scores', function (Blueprint $table) {
            $table->dropForeign(['interview_factor_id']);
        });
        Schema::table('interview_scores', function (Blueprint $table) {
            $table->renameColumn('interview_factor_id', 'interview_question_id');
        });
        Schema::table('interview_scores', function (Blueprint $table) {
            $table->foreign('interview_question_id')->references('id')->on('interview_questions')->restrictOnDelete();
        });

        Schema::create('question_sets', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 500)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('question_set_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('question_set_id')->constrained()->cascadeOnDelete();
            $table->foreignId('interview_question_id')->constrained()->restrictOnDelete();
            $table->decimal('weight', 5, 2)->default(0);       // % in this set
            $table->unsignedSmallInteger('sequence')->default(0);   // order on the score sheet
            $table->timestamps();

            $table->unique(['question_set_id', 'interview_question_id']);
        });

        Schema::create('interview_stages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('job_posting_id')->constrained()->restrictOnDelete();
            $table->string('name', 100);
            $table->foreignId('question_set_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('created_by_user_id')->nullable();
            $table->timestamps();

            $table->unique(['job_posting_id', 'name']);
        });

        Schema::table('interviews', function (Blueprint $table) {
            $table->foreignId('interview_stage_id')->nullable()->after('application_id')->constrained()->restrictOnDelete();
        });

        // ---- existing data ----
        $now = now();
        $setId = null;
        $interviews = DB::table('interviews')->join('applications', 'applications.id', '=', 'interviews.application_id')
            ->select('interviews.id', 'interviews.round', 'applications.job_posting_id')->get();
        $questions = DB::table('interview_questions')->where('is_active', true)->orderBy('sequence')->orderBy('name')->get();

        if ($questions->isNotEmpty() || $interviews->isNotEmpty()) {
            $setId = DB::table('question_sets')->insertGetId([
                'name' => 'General', 'description' => 'Made from the former interview factors.', 'is_active' => true,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            foreach ($questions->values() as $i => $question) {
                DB::table('question_set_items')->insert([
                    'question_set_id' => $setId, 'interview_question_id' => $question->id,
                    'weight' => $question->weight, 'sequence' => $i + 1, 'created_at' => $now, 'updated_at' => $now,
                ]);
            }
        }

        foreach ($interviews->groupBy(fn ($row) => $row->job_posting_id.'-'.$row->round) as $rows) {
            $first = $rows->first();
            $stageId = DB::table('interview_stages')->insertGetId([
                'job_posting_id' => $first->job_posting_id, 'name' => 'Round '.$first->round, 'question_set_id' => $setId,
                'created_at' => $now, 'updated_at' => $now,
            ]);
            DB::table('interviews')->whereIn('id', $rows->pluck('id'))->update(['interview_stage_id' => $stageId]);
        }

        Schema::table('interviews', function (Blueprint $table) {
            $table->dropForeign(['interview_stage_id']);
        });
        Schema::table('interviews', function (Blueprint $table) {
            $table->unsignedBigInteger('interview_stage_id')->nullable(false)->change();
            $table->foreign('interview_stage_id')->references('id')->on('interview_stages')->restrictOnDelete();
        });

        Schema::table('interview_questions', function (Blueprint $table) {
            $table->dropColumn(['weight', 'is_required']);
            $table->string('name', 255)->change();   // a question can be longer than a factor's name
        });
    }

    public function down(): void
    {
        Schema::table('interview_questions', function (Blueprint $table) {
            $table->decimal('weight', 5, 2)->default(0)->after('description');
            $table->boolean('is_required')->default(false)->after('weight');
        });
        Schema::table('interviews', function (Blueprint $table) {
            $table->dropConstrainedForeignId('interview_stage_id');
        });
        Schema::dropIfExists('interview_stages');
        Schema::dropIfExists('question_set_items');
        Schema::dropIfExists('question_sets');

        Schema::table('interview_scores', function (Blueprint $table) {
            $table->dropForeign(['interview_question_id']);
        });
        Schema::table('interview_scores', function (Blueprint $table) {
            $table->renameColumn('interview_question_id', 'interview_factor_id');
        });
        Schema::rename('interview_questions', 'interview_factors');
        Schema::table('interview_scores', function (Blueprint $table) {
            $table->foreign('interview_factor_id')->references('id')->on('interview_factors')->restrictOnDelete();
        });
    }
};
