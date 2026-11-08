<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Education (legacy `academics`) and earlier jobs (legacy `workhistory`) of an employee. [EMP-03]
 *
 *   score + out_of     "3.75" out of "4.00" (GPA / CGPA) or "65" out of "100" (%). Legacy had only score.
 *   attachment_path    file on the tenant disk (certificate, experience letter); attachment_name = the
 *                      name it had when uploaded, used for the download.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_educations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('degree', 100);              // e.g. "B Sc (Hon's)", "HSC"
            $table->string('institution', 300)->nullable();
            $table->string('subject', 200)->nullable();
            $table->decimal('score', 6, 2)->nullable();
            $table->decimal('out_of', 6, 2)->nullable();
            $table->unsignedSmallInteger('passing_year')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_work_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('organization', 300);
            $table->string('designation', 100)->nullable();
            $table->date('from_date')->nullable();
            $table->date('to_date')->nullable();
            $table->string('leaving_reason')->nullable();
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_work_histories');
        Schema::dropIfExists('employee_educations');
    }
};
