<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TENANT lookup tables for recruitment (6.1). [REC-04, REC-05]
 *
 *   interview_factors   legacy `interviewfactors` (type 0): what interviewers score, e.g. "Communication".
 *                       Each interviewer gives a score per factor in 6.6; `weight` decides how much it counts.
 *   rejection_reasons   new: picked when an application is rejected or withdrawn (6.5).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('interview_factors', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('description', 500)->nullable();    // a hint for interviewers: what to look for
            $table->decimal('weight', 5, 2)->default(0);        // % share of the total score (legacy "Weight (%)")
            $table->boolean('is_required')->default(false);     // legacy "Required Competency": must be scored
            $table->unsignedInteger('sequence')->default(0);    // order on the score sheet
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index('sequence');
        });

        Schema::create('rejection_reasons', function (Blueprint $table) {
            $table->id();
            $table->string('name', 150)->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rejection_reasons');
        Schema::dropIfExists('interview_factors');
    }
};
