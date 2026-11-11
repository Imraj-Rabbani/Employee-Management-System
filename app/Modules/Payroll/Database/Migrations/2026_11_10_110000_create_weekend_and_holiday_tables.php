<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TENANT tables for days off. [TA-02]
 *
 *   weekend_profiles          legacy `weekendprofiles`: the weekly days off, e.g. Friday + Saturday
 *   holidays                  legacy `holidays`: one public / festival holiday on a date
 *   holiday_profiles          legacy `holidayprofiles`: a holiday calendar, e.g. "Head office", "Factory"
 *   holiday_holiday_profile   legacy `holidayitems`: which holidays are in which calendar
 *
 * Employees get one weekend profile and one holiday profile (employees.weekend_profile_id / holiday_profile_id).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekend_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->nullable()->unique();
            $table->string('name', 100)->unique();
            // Weekday 0 = Sunday … 6 = Saturday (App\Modules\Payroll\Enums\Weekday). NULL = no such day.
            $table->unsignedTinyInteger('day_one')->nullable();
            $table->unsignedTinyInteger('day_two')->nullable();
            // NULL = off every week; 1 = odd / 2 = even occurrences in the year (Enums\WeekParity).
            // Legacy kept this in two columns (alterOne + typeOne); one column says the same.
            $table->unsignedTinyInteger('alternate_one')->nullable();
            $table->unsignedTinyInteger('alternate_two')->nullable();
            // A weekday worked from home (legacy `homeOffice` is a day, not yes/no). NULL = none.
            $table->unsignedTinyInteger('home_office_day')->nullable();
            $table->string('detail', 1000)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('holidays', function (Blueprint $table) {
            $table->id();
            $table->date('date');
            $table->string('title', 100);
            $table->boolean('is_public')->default(true);       // government holiday
            $table->boolean('is_festival')->default(false);    // festival (e.g. Eid): may affect festival bonus / OT rate
            // Same day and month every year from `date` on (legacy: year = 0), e.g. 21 February.
            $table->boolean('repeats_yearly')->default(false);
            $table->string('detail', 1000)->nullable();
            $table->timestamps();

            $table->unique(['date', 'title']);
        });

        Schema::create('holiday_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('detail', 1000)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Pivot: singular names in alphabetical order (Laravel's default for belongsToMany).
        Schema::create('holiday_holiday_profile', function (Blueprint $table) {
            $table->foreignId('holiday_id')->constrained()->cascadeOnDelete();
            $table->foreignId('holiday_profile_id')->constrained()->cascadeOnDelete();
            $table->primary(['holiday_id', 'holiday_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_holiday_profile');
        Schema::dropIfExists('holiday_profiles');
        Schema::dropIfExists('holidays');
        Schema::dropIfExists('weekend_profiles');
    }
};
