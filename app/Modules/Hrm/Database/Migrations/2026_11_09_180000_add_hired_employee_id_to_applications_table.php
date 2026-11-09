<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * applications.hired_employee_id: the employee record made from this application by the hire (6.8). [REC-07]
 * Links the hiring history to the employee (and stops the same application being hired twice).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->foreignId('hired_employee_id')->nullable()->after('stage_changed_at')->constrained('employees')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('applications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('hired_employee_id');
        });
    }
};
