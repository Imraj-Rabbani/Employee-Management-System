<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TENANT tables for leave balances (8.2). [LV-03] Architecture §11.
 *
 *   leave_ledger    APPEND-ONLY: every change of a balance is one row (+ adds days, − takes them).
 *                   Rows are never edited or deleted: a mistake is fixed with a new row (correction or reversal).
 *   leave_balances  a CACHE of SUM(ledger) per employee, leave type and leave year, split by entry type,
 *                   so lists and reports need no sums. Rebuilt by LeaveLedger::refresh() after every write.
 *
 * `year` = the LEAVE year, named after the calendar year it starts in (setting leave.year_start_month;
 * July 2026 – June 2027 = 2026). See App\Modules\Hrm\Data\LeaveYear.
 * Amounts decimal(7,2): accrual (8.5) earns fractions such as 0.85 day.
 *
 * Nothing in the legacy system matches this: it recomputed balances from `leaves` + profile values every time.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();   // a type with entries cannot be deleted
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('entry_type');           // LeaveEntryType
            $table->decimal('days', 7, 2);                       // + credit, − debit
            $table->date('entry_date');
            $table->date('expires_on')->nullable();              // carried-forward days: first day they are no longer valid
            $table->nullableMorphs('source');                    // what caused it: a leave application, an encashment, a carry-forward row …
            $table->string('note', 500)->nullable();
            $table->unsignedBigInteger('created_by_user_id')->nullable();   // central users table: no FK across databases
            $table->timestamps();

            $table->index(['employee_id', 'leave_type_id', 'year']);
        });

        Schema::create('leave_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();
            $table->unsignedSmallInteger('year');
            // Added (signed: carried_forward is − in the year the days left, + in the year they arrived).
            $table->decimal('opening', 7, 2)->default(0);
            $table->decimal('earned', 7, 2)->default(0);
            $table->decimal('carried_forward', 7, 2)->default(0);
            $table->decimal('adjusted', 7, 2)->default(0);
            // Used (shown as positive numbers).
            $table->decimal('taken', 7, 2)->default(0);
            $table->decimal('encashed', 7, 2)->default(0);
            $table->decimal('expired', 7, 2)->default(0);
            $table->decimal('balance', 7, 2)->default(0);         // = SUM(ledger.days)
            $table->timestamps();

            $table->unique(['employee_id', 'leave_type_id', 'year']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leave_balances');
        Schema::dropIfExists('leave_ledger');
    }
};
