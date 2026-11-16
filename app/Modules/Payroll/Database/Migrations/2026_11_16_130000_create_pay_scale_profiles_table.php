<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pay scale profiles = how payroll treats a group of employees (legacy `payscaleprofiles`): arrears, the joining
 * threshold day and leave without pay. Read by the payroll run.
 * Employees without one use the default from the payroll settings.
 *
 * Not here: the legacy per-grade increment rules (`payscaleitems`: increment %, basis, max times, minimum stay):
 * they belong to increments.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pay_scale_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('code', 50)->unique();
            $table->string('name', 100)->unique();
            $table->boolean('pays_arrear')->default(true);               // back-dated changes / late joiners paid as arrears
            $table->unsignedTinyInteger('threshold_day')->default(0);    // joined after this day → paid from next month; 0 = none
            $table->boolean('deduct_lwp')->default(true);                // leave without pay reduces the salary
            $table->boolean('ignore_late_lwp')->default(false);          // ... but not the LWP that comes from late attendance
            $table->string('detail', 1000)->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // employees.pay_scale_profile_id was a plain id: add the foreign key now the table exists.
        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('pay_scale_profile_id')->references('id')->on('pay_scale_profiles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['pay_scale_profile_id']);
        });
        Schema::dropIfExists('pay_scale_profiles');
    }
};
