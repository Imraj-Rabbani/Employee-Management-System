<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Uploaded Excel files of employee salaries and what happened to them. Same shape as employee_imports.
 *   user_id   who uploaded it (central users.id, no FK): the job imports with that user's org-unit access
 *   errors    JSON list of {row, column, message}: the validation report
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_imports', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id')->index();
            $table->string('file_path');
            $table->string('file_name');
            $table->string('status', 20)->default('pending');
            $table->unsignedInteger('total_rows')->default(0);
            $table->unsignedInteger('imported_rows')->default(0);
            $table->json('errors')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('salary_imports');
    }
};
