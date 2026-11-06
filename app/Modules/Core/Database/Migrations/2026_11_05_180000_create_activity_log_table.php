<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TENANT table: who changed what, when (spatie/laravel-activitylog). [FND-06]
 * Filled automatically for every TenantModel; shown by the History component.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('activity_log', function (Blueprint $table) {
            $table->id();
            $table->string('log_name')->nullable()->index();
            $table->text('description');
            $table->nullableMorphs('subject', 'subject');      // the record that changed, e.g. Role #3
            $table->string('event')->nullable();               // created / updated / deleted
            $table->nullableMorphs('causer', 'causer');        // who did it: a central users.id
            $table->json('attribute_changes')->nullable();     // {"old": {...}, "attributes": {...}}
            $table->json('properties')->nullable();
            $table->timestamps();

            $table->index('created_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activity_log');
    }
};
