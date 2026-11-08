<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Family members and nominees of an employee. [EMP-02]
 *
 *   relation        EmployeeRelation enum (tinyint, legacy numbers), not a string as schema.mmd says.
 *   share_percent   decimal(5,2): 33.33 + 33.33 + 33.34 = 100 exactly (floats would drift).
 *   guardian        JSON {name, national_id, phone, relation}: only for a minor nominee.
 *
 * Both are deleted with the employee (cascade): they mean nothing on their own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_family_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('relation')->nullable();
            $table->string('name', 200);
            $table->string('name_local', 200)->nullable();
            $table->date('birth_date')->nullable();
            $table->unsignedTinyInteger('gender')->nullable();
            $table->string('occupation', 100)->nullable();
            $table->string('phone', 30)->nullable();
            $table->timestamps();
        });

        Schema::create('employee_nominees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('name', 200);
            $table->unsignedTinyInteger('relation')->nullable();
            $table->string('national_id', 30)->nullable();
            $table->string('phone', 30)->nullable();
            $table->string('address', 500)->nullable();
            $table->decimal('share_percent', 5, 2);
            $table->boolean('is_minor')->default(false);
            $table->json('guardian')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_nominees');
        Schema::dropIfExists('employee_family_members');
    }
};
