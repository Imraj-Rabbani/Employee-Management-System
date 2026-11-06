<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The activity log moved to each company's own database (task 2.10, Core module migration),
 * because what it records (employees, salaries...) belongs to one company.
 * This removes the copy that task 1.2c had created in the central database.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::dropIfExists('activity_log');
    }

    public function down(): void
    {
        // Nothing: the central table is not coming back.
    }
};
