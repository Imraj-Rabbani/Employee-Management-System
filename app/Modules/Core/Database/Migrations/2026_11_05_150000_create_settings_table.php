<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TENANT table (runs with `php artisan tenants:migrate`): one company's settings.
 * Legacy: the `config` table. Read and write through App\Modules\Core\Services\Settings, not directly.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 100)->unique();     // e.g. "timezone", "currency", "payroll.rounding"
            $table->json('value')->nullable();         // stored as JSON so numbers, booleans and lists keep their type
            $table->boolean('autoload')->default(true); // true = loaded (and cached) together on first use
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
