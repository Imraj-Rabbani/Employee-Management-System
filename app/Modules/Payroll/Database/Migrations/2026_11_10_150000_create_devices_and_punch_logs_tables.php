<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TENANT tables for punches (raw clock-ins and clock-outs). [TA-04]
 *
 *   devices      the ZKTeco access-control devices the company has (synced every 5 minutes, 7.3d)
 *   punch_logs   legacy `punchlog`: every punch from every source, never edited. Attendance processing (7.4)
 *                reads them; mistakes are fixed with attendance corrections (7.6), not by changing punches.
 *
 * A punch is matched to an employee by access_code (= employees.access_code, "Attendance ID").
 * employee_id stays NULL when no employee has that code yet; PunchLog::linkEmployees() fills it later.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('devices', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('serial_number', 100)->nullable()->unique();
            $table->string('ip', 45);                                  // IPv4 or IPv6
            $table->unsignedInteger('port')->default(4370);            // ZKTeco default
            // Where it hangs (information, and the default unit for reports). NULL = not tied to a unit.
            $table->foreignId('org_unit_id')->nullable()->constrained()->restrictOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestamp('last_synced_at')->nullable();
            $table->string('last_sync_error', 500)->nullable();        // NULL = last sync worked
            $table->timestamps();
        });

        Schema::create('punch_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->nullable()->constrained()->restrictOnDelete();
            $table->string('access_code', 50)->nullable()->index();
            $table->dateTime('punched_at');
            $table->unsignedTinyInteger('source');                     // Enums\PunchSource
            $table->foreignId('device_id')->nullable()->constrained()->nullOnDelete();
            // Web / mobile punch: where the person was, and how far from the shift's geofence point (7.3e).
            $table->decimal('latitude', 10, 7)->nullable();
            $table->decimal('longitude', 10, 7)->nullable();
            $table->unsignedInteger('distance_m')->nullable();
            // Rows of one CSV upload share a batch id, so a wrong upload can be found (and removed) as a whole.
            $table->uuid('batch')->nullable()->index();
            $table->unsignedBigInteger('created_by_user_id')->nullable();   // central users.id (CSV / manual)
            $table->timestamps();

            $table->index(['employee_id', 'punched_at']);              // attendance (7.4): "punches of X on day Y"
            // A device sends the same punch again on every sync: the same code, time and device is stored once.
            $table->unique(['device_id', 'access_code', 'punched_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('punch_logs');
        Schema::dropIfExists('devices');
    }
};
