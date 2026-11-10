<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Applicants entered by HR, and their applications to job postings (6.4). [REC-03]
 *
 *   applicants                one row per PERSON (the company's talent pool). email and phone are unique
 *                             (NULLs allowed: MySQL lets many rows have NULL in a unique column), so the
 *                             same person cannot be entered twice. Stored normalised: email lower case,
 *                             phone digits only, Bangladesh "+880 1711-000000" → "01711000000".
 *   applicant_educations      like employee_educations, without certificate files
 *   applicant_work_histories  like employee_work_histories, without files
 *   applications              a person applying to ONE posting; the same person can apply to several
 *                             postings, but only once to each (unique). stage = ApplicationStage.
 *                             6.5 adds the stage history.
 *
 * Deleting: an applicant with applications cannot be deleted (restrict), so the hiring history stays.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('applicants', function (Blueprint $table) {
            $table->id();
            $table->string('first_name', 100);
            $table->string('last_name', 100)->nullable();
            $table->string('email', 150)->nullable()->unique();
            $table->string('phone', 30)->nullable()->unique();
            $table->unsignedTinyInteger('gender')->nullable();          // Gender enum
            $table->date('birth_date')->nullable();
            $table->string('national_id', 30)->nullable();
            $table->string('address', 500)->nullable();
            $table->string('current_employer', 200)->nullable();
            $table->string('current_designation', 100)->nullable();
            $table->decimal('expected_salary', 14, 2)->nullable();     // monthly gross, compared in 6.7
            $table->string('cv_path')->nullable();                      // tenant disk, applicants/cv
            $table->string('cv_name')->nullable();                      // the file name when uploaded
            $table->text('notes')->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();   // central users.id
            $table->timestamps();
        });

        Schema::create('applicant_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->string('degree', 100);
            $table->string('institution', 300)->nullable();
            $table->string('subject', 200)->nullable();
            $table->decimal('score', 6, 2)->nullable();
            $table->decimal('out_of', 6, 2)->nullable();
            $table->unsignedSmallInteger('passing_year')->nullable();
            $table->timestamps();
        });

        Schema::create('applicant_work_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->cascadeOnDelete();
            $table->string('organization', 300);
            $table->string('designation', 100)->nullable();
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->string('leaving_reason')->nullable();
            $table->timestamps();
        });

        Schema::create('applications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('applicant_id')->constrained()->restrictOnDelete();
            $table->foreignId('job_posting_id')->constrained()->restrictOnDelete();
            $table->string('stage', 20)->default('applied');
            $table->date('applied_on');
            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();
            $table->timestamps();

            $table->unique(['applicant_id', 'job_posting_id']);   // once per posting
            $table->index(['job_posting_id', 'stage']);           // the pipeline of one posting (6.5)
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('applications');
        Schema::dropIfExists('applicant_work_histories');
        Schema::dropIfExists('applicant_educations');
        Schema::dropIfExists('applicants');
    }
};
