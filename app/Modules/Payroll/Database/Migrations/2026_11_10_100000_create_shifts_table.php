<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TENANT table `shifts` (legacy `shifts`): the working hours an employee is expected to keep. [TA-01]
 *
 * Used by attendance processing (7.4) to decide present / late / early exit / half day / overtime.
 * Example: "General" 09:00–18:00, 15 minutes grace. A night shift 22:00–06:00 has is_overnight = true.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shifts', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100);
            // NULL = for the whole company; otherwise the shift is offered for this org unit.
            $table->foreignId('org_unit_id')->nullable()->constrained('org_units')->restrictOnDelete();

            $table->time('start_time');
            $table->time('end_time');
            $table->decimal('hours', 4, 2);                                  // expected working hours, e.g. 8.00

            $table->unsignedSmallInteger('grace_minutes')->default(0);        // arrive this late and still be "on time"
            $table->unsignedSmallInteger('grace_limit_per_month')->nullable(); // how often grace may be used; NULL = always
            $table->unsignedSmallInteger('flexibility_minutes')->default(0);  // may start later if staying later too

            $table->decimal('half_day_hours', 4, 2)->nullable();             // worked less than this → half day
            $table->time('half_day_after')->nullable();                       // arrived after this time → half day

            $table->boolean('is_overnight')->default(false);                  // ends on the next calendar day
            $table->boolean('is_default')->default(false);                    // used when an employee has no shift

            // Web / mobile punch must be within radius_m metres of this point (7.3).
            $table->boolean('geofence_enabled')->default(false);
            $table->decimal('geofence_lat', 10, 7)->nullable();
            $table->decimal('geofence_lng', 10, 7)->nullable();
            $table->unsignedInteger('geofence_radius_m')->nullable();

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('shifts');
    }
};
