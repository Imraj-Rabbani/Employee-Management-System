<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * employees.shift_id / weekend_profile_id / holiday_profile_id were plain ids (Phase 5) because these tables
 * did not exist yet. Now they do: add the foreign keys. [TA-01, TA-02]
 *
 * restrictOnDelete: a shift or profile that employees still use cannot be deleted (the setup form then
 * says "still in use, make it inactive instead").
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('shift_id')->references('id')->on('shifts')->restrictOnDelete();
            $table->foreign('weekend_profile_id')->references('id')->on('weekend_profiles')->restrictOnDelete();
            $table->foreign('holiday_profile_id')->references('id')->on('holiday_profiles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['shift_id']);
            $table->dropForeign(['weekend_profile_id']);
            $table->dropForeign(['holiday_profile_id']);
        });
    }
};
