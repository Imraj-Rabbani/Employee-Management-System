<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Documents (with expiry) and bank accounts of an employee. [EMP-01, EMP-03]
 *
 *   employee_documents.type      DocumentType enum. title = free name for "Other".
 *   attachment_path / _name      same columns as education / work history (schema.mmd said file_path), so the
 *                                shared upload code (EmployeeRecords) works unchanged.
 *   employee_bank_accounts       exactly ONE is_primary per employee once there is any account: salary
 *                                goes there (Phase 12). Kept true by the BankAccounts component.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_documents', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->unsignedTinyInteger('type');
            $table->string('title', 150)->nullable();
            $table->string('number', 100)->nullable();
            $table->date('issue_date')->nullable();
            $table->date('expiry_date')->nullable()->index();   // "expiring soon" lists
            $table->string('attachment_path')->nullable();
            $table->string('attachment_name')->nullable();
            $table->timestamps();
        });

        Schema::create('employee_bank_accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->string('bank_name', 150);
            $table->string('branch_name', 150)->nullable();
            $table->string('routing_no', 20)->nullable();
            $table->string('account_name', 200)->nullable();
            $table->string('account_no', 50);
            $table->boolean('is_primary')->default(false);
            $table->timestamps();

            $table->unique(['employee_id', 'account_no']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_bank_accounts');
        Schema::dropIfExists('employee_documents');
    }
};
