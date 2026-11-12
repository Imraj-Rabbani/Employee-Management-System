<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * TENANT tables for leave setup (8.1). [LV-01, LV-02]
 *
 *   leave_types          (legacy `leavetypes`)          Casual leave, Sick leave ... and ALL their rules
 *   leave_profiles       (legacy `leaveprofiles`)       "Staff", "Workers" ... assigned to employees
 *   leave_profile_items  (legacy `leaveitems`)          days per year of each leave type in a profile
 *
 * The rules are only STORED here. They are checked by LeaveRules (8.3), earned by the accrual job (8.5),
 * carried forward by CarryForwardLeave (8.5), encashed in 8.6.
 *
 * Day counts are decimal(5,1) so half days (0.5) fit. NULL = "no limit" / "not used", never 0:
 * 0 is a real value (e.g. attachment needed for MORE than 0 days = always).
 *
 * Legacy columns NOT ported: lfa / minForLfa (leave fare assistance), deductionPriority / mandatoryPriority
 * (absences taken from leave balance automatically), lwpLimit, hideInReport / hideInEmail, onHoldSalary,
 * monthPerCycle, applicationAllowedDay, eligibleAfterPermanent (= eligible_from + eligible_after_months),
 * eligibleServiceMonthsTo, isBulk, proportionate, encashRule. Add them when a company needs one.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('leave_types', function (Blueprint $table) {
            $table->id();
            $table->string('code', 20)->unique();                       // short, shown in calendars: CL, SL, EL
            $table->string('name', 100)->unique();
            $table->text('detail')->nullable();
            $table->unsignedSmallInteger('sequence')->default(0);       // order in lists and reports

            // Who may take it.
            $table->unsignedTinyInteger('gender')->nullable();          // Gender enum; NULL = everyone
            $table->unsignedTinyInteger('religion')->nullable();        // Religion enum; NULL = everyone
            $table->unsignedTinyInteger('eligible_from')->default(0);   // LeaveEligibleFrom: join or confirmation date
            $table->unsignedSmallInteger('eligible_after_months')->default(0);
            $table->unsignedSmallInteger('max_times_in_service')->nullable();   // e.g. maternity: 2 times; NULL = no limit
            $table->boolean('hr_only')->default(false);                 // not offered in Self Service, HR enters it

            // One application.
            $table->decimal('min_days', 5, 1)->nullable();
            $table->decimal('max_days', 5, 1)->nullable();
            $table->unsignedSmallInteger('max_calendar_days')->nullable();      // from–to span incl. weekends
            $table->boolean('allow_half_day')->default(false);
            $table->boolean('exclude_holidays')->default(false);        // weekends / holidays inside the leave are not counted
            $table->unsignedTinyInteger('bridging')->default(0);        // LeaveBridging: holidays touching the leave added to it
            $table->unsignedSmallInteger('notice_days')->default(0);    // apply at least N days before
            $table->boolean('allow_post_facto')->default(false);        // may apply after the leave started
            $table->unsignedSmallInteger('post_facto_days')->nullable(); // ... up to N days after; NULL = any time
            $table->boolean('reason_required')->default(false);
            $table->decimal('attachment_after_days', 5, 1)->nullable(); // attachment needed for MORE than N days; NULL = never

            // Balance.
            $table->boolean('is_lwp')->default(false);                  // leave without pay: payroll deducts it
            $table->boolean('requires_balance')->default(true);         // may not go below 0
            $table->decimal('max_balance', 5, 1)->nullable();           // at most this much can be available

            // Earning (8.5).
            $table->unsignedTinyInteger('earn_type')->default(0);       // LeaveEarnType
            $table->decimal('earn_per_days_worked', 5, 2)->nullable();  // PerDaysWorked: 1 day per N days

            // Year end (8.5).
            $table->boolean('can_carry_forward')->default(false);
            $table->decimal('carry_forward_limit', 5, 1)->nullable();   // NULL = everything left
            $table->unsignedSmallInteger('carry_forward_expiry_months')->nullable();  // NULL = never expires

            // Encashment (8.6).
            $table->boolean('is_encashable')->default(false);
            $table->decimal('encash_limit', 5, 1)->nullable();          // days per encashment; NULL = all

            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('leave_profiles', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->text('detail')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // A leave type NOT in an employee's profile cannot be taken by them. In it with 0 days = may be taken
        // when the type needs no balance (e.g. leave without pay).
        Schema::create('leave_profile_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('leave_profile_id')->constrained()->cascadeOnDelete();
            $table->foreignId('leave_type_id')->constrained()->restrictOnDelete();   // a type in use cannot be deleted
            $table->decimal('days_per_year', 5, 1)->default(0);
            $table->timestamps();

            $table->unique(['leave_profile_id', 'leave_type_id']);
        });

        // employees.leave_profile_id was a plain id since Phase 5: now the table exists.
        Schema::table('employees', function (Blueprint $table) {
            $table->foreign('leave_profile_id')->references('id')->on('leave_profiles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropForeign(['leave_profile_id']);
        });
        Schema::dropIfExists('leave_profile_items');
        Schema::dropIfExists('leave_profiles');
        Schema::dropIfExists('leave_types');
    }
};
