<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * 10.5: whether ABSENT days (attendance_days: absent = 1, half day = 0.5) reduce the salary, like deduct_lwp does
 * for leave without pay. Per pay scale profile, so a group without punches (management, field staff) can be
 * left alone. On by default, as LWP.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('pay_scale_profiles', function (Blueprint $table) {
            $table->boolean('deduct_absent')->default(true)->after('ignore_late_lwp');
        });
    }

    public function down(): void
    {
        Schema::table('pay_scale_profiles', function (Blueprint $table) {
            $table->dropColumn('deduct_absent');
        });
    }
};
