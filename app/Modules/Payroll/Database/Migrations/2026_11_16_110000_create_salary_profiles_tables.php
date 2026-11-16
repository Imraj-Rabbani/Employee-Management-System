<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Salary profiles = salary structures, usually one per grade (legacy `salaryprofiles` + `salaryheadvalues`). [PC-02]
 * An item says what one head is worth in the profile: a fixed amount, or a percent of basic / gross.
 * The basic head (salary_heads.is_basic) is never an item: it is the base everything else is worked out from.
 *
 * Not built: legacy cycle / arrear type / arrear head (arrears come with increments, Phase 12) and the
 * per-item effective date (employee salaries are effective-dated instead, 9.4).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('salary_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100)->unique();
            $table->foreignId('grade_id')->nullable()->constrained()->restrictOnDelete();   // NULL = any grade
            $table->string('detail', 1000)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('salary_profile_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('salary_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_head_id')->constrained()->restrictOnDelete();
            $table->string('value_type', 20);              // ProfileValueType
            $table->decimal('amount', 15, 2)->nullable();  // value_type amount
            $table->decimal('rate', 9, 4)->nullable();     // value_type percent of basic / gross
            $table->timestamps();

            $table->unique(['salary_profile_id', 'salary_head_id']);
        });

        // employees.salary_profile_id was a plain id (Phase 5): now the table exists, add the foreign key.
        // restrictOnDelete: a profile employees still use cannot be deleted ("make it inactive instead").
        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('salary_profile_id')->references('id')->on('salary_profiles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['salary_profile_id']);
        });
        Schema::dropIfExists('salary_profile_items');
        Schema::dropIfExists('salary_profiles');
    }
};
