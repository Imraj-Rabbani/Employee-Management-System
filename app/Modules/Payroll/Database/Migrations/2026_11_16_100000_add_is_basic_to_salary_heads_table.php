<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Marks THE basic salary head (schema.mmd `is_basic`, left out of the first salary heads migration). At most one head has it; salary profiles
 * work out every other head from it, and from the gross.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('salary_heads', function (Blueprint $table) {
            $table->boolean('is_basic')->default(false)->after('calc_method');
        });
    }

    public function down(): void
    {
        Schema::table('salary_heads', function (Blueprint $table) {
            $table->dropColumn('is_basic');
        });
    }
};
