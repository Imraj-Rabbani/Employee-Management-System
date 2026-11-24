<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The pricing plan of each company: "free" or "business" (App\Core\Enums\Plan).
 * New companies start on Free unless they choose Business at sign-up.
 * Companies that existed before plans were added are put on Business, so none of them is
 * suddenly over a limit (the platform admin can change it in Edit company).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->string('plan', 20)->default('free')->after('name');
        });

        DB::table('tenants')->update(['plan' => 'business']);
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('plan');
        });
    }
};
