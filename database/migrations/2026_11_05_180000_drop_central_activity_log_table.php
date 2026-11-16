<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

/**
 * The activity log moved to each company's own database (Core module migration),
 * because what it records (employees, salaries...) belongs to one company.
 * This removes the earlier copy in the central database.
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
