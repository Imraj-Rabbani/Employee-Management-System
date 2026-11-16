<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Employee salaries: an effective-dated amount per head (legacy `salaries`).
 *
 * A salary is a SET of rows with the same effective_from (one per head). A change never overwrites: the open set
 * is closed the day before (effective_to) and a new set starts (Actions\SetEmployeeSalary). value_type / rate say
 * how a row was worked out ("50% of basic"); NULL = the basic or an amount typed by hand. The payroll run
 * reads the rows valid in its period; `amount` is what counts.
 *
 * Hold flags are per EMPLOYEE (not per row as in schema.mmd): "hold the salary" / "hold the bank transfer" apply to
 * whatever salary is current, until switched off. Not built: currency per row (one company currency).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_salaries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('salary_head_id')->constrained()->restrictOnDelete();
            $table->string('value_type', 20)->nullable();   // ProfileValueType, NULL = basic / typed amount
            $table->decimal('rate', 9, 4)->nullable();
            $table->decimal('amount', 15, 2);
            $table->date('effective_from');
            $table->date('effective_to')->nullable();
            $table->foreignId('salary_profile_id')->nullable()->constrained()->nullOnDelete();   // the profile used, for information
            $table->timestamps();

            $table->unique(['employee_id', 'salary_head_id', 'effective_from'], 'employee_salaries_head_from_unique');   // default name is over MySQL's 64 chars
            $table->index(['employee_id', 'effective_from']);
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->boolean('salary_on_hold')->default(false)->after('salary_profile_id');
            $table->boolean('bank_transfer_on_hold')->default(false)->after('salary_on_hold');
            $table->string('salary_hold_reason', 500)->nullable()->after('bank_transfer_on_hold');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn(['salary_on_hold', 'bank_transfer_on_hold', 'salary_hold_reason']);
        });
        Schema::dropIfExists('employee_salaries');
    }
};
