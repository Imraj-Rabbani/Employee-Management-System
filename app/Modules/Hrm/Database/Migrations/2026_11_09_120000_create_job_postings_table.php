<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A job advertisement made from an APPROVED manpower request (6.3). Applicants apply to a posting (6.4). [REC-02]
 *
 *   manpower_request_id  required: no posting without an approved request. One request may get more than
 *                        one posting (e.g. advertised again after closing), but their openings together
 *                        may not exceed the request's headcount still to fill (checked in the form, 6.3b).
 *   openings             how many people this posting hires
 *   open_date/close_date when applications are taken; close_date NULL = until closed by hand
 *   status               'draft' / 'open' / 'closed' (JobPostingStatus)
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('job_postings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('manpower_request_id')->constrained()->restrictOnDelete();
            $table->string('title', 150);
            $table->text('description')->nullable();    // the job: what the person will do
            $table->text('requirements')->nullable();   // education, experience, skills
            $table->unsignedSmallInteger('openings');
            $table->date('open_date');
            $table->date('close_date')->nullable();
            $table->string('status', 20)->default('draft');
            $table->unsignedBigInteger('created_by_user_id')->nullable()->index();   // central users.id
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('job_postings');
    }
};
