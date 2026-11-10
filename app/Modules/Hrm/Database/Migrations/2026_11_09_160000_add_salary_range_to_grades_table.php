<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A pay band per grade: the monthly gross a person in this grade normally earns (6.7, decided 2026-11-09).
 * An offer (6.7) shows whether its proposed salary is inside the band. Not in legacy; both optional.
 * Salary PROFILES per grade (the head-by-head template) come separately in Phase 9.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->decimal('salary_min', 14, 2)->nullable()->after('sequence');
            $table->decimal('salary_max', 14, 2)->nullable()->after('salary_min');
        });
    }

    public function down(): void
    {
        Schema::table('grades', function (Blueprint $table) {
            $table->dropColumn(['salary_min', 'salary_max']);
        });
    }
};
