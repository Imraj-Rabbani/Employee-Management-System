<?php

namespace Tests\Feature\Hrm;

use App\Modules\Hrm\Enums\BloodGroup;
use App\Modules\Hrm\Enums\EmployeeStatus;
use App\Modules\Hrm\Enums\Gender;
use App\Modules\Hrm\Enums\MaritalStatus;
use App\Modules\Hrm\Enums\PaymentMethod;
use App\Modules\Hrm\Enums\Religion;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Tests\TestCase;

/**
 * Task 4.4: HRM enums ported from legacy Hrm/Constants.
 *
 * The numbers MUST stay equal to legacy's: the SSS data migration (Phase 15) copies them as they are.
 * If one of these tests fails, someone renumbered an enum: put the number back.
 */
class HrmEnumsTest extends TestCase
{
    public function test_the_numbers_are_the_same_as_legacy(): void
    {
        $this->assertSame([1 => 'Male', 2 => 'Female'], Gender::options());
        $this->assertSame([1 => 'Islam', 2 => 'Hinduism', 3 => 'Christianity', 4 => 'Buddhism', 5 => 'Other'], Religion::options());
        $this->assertSame([1 => 'Single', 2 => 'Married', 3 => 'Divorced', 4 => 'Widow/Widower'], MaritalStatus::options());
        $this->assertSame([1 => 'A+', 2 => 'B+', 3 => 'AB+', 4 => 'O+', 5 => 'A-', 6 => 'B-', 7 => 'AB-', 8 => 'O-'], BloodGroup::options());
        $this->assertSame([1 => 'Cash', 2 => 'Bank'], PaymentMethod::options());
        $this->assertSame([1 => 'Active', 2 => 'Suspended', 3 => 'Separated'], EmployeeStatus::options());
    }

    public function test_legacy_zero_none_is_not_a_value(): void
    {
        foreach ([Gender::class, Religion::class, MaritalStatus::class, BloodGroup::class, PaymentMethod::class, EmployeeStatus::class] as $enum) {
            $this->assertNull($enum::tryFrom(0), $enum.' must not accept 0: store NULL instead.');
        }
    }

    public function test_label_for_handles_null_and_unknown_values(): void
    {
        $this->assertSame('Female', Gender::labelFor(2));
        $this->assertSame('', Gender::labelFor(null));
        $this->assertSame('', Gender::labelFor(9));
        $this->assertSame([1, 2], PaymentMethod::values());
    }

    public function test_labels_follow_the_language_but_blood_groups_stay_symbols(): void
    {
        app()->setLocale('bn');

        $this->assertSame('নারী', Gender::Female->label());
        $this->assertSame('বিবাহিত', MaritalStatus::Married->label());
        $this->assertSame('ব্যাংক', PaymentMethod::Bank->label());
        $this->assertSame('AB+', BloodGroup::ABPositive->label());

        app()->setLocale('en');
    }

    public function test_employee_status_colours_and_employment(): void
    {
        $this->assertSame('success', EmployeeStatus::Active->color());
        $this->assertTrue(EmployeeStatus::Suspended->isEmployed());
        $this->assertFalse(EmployeeStatus::Separated->isEmployed());
    }

    public function test_enums_validate_form_input(): void
    {
        $rules = ['gender' => ['nullable', Rule::enum(Gender::class)]];

        $this->assertTrue(Validator::make(['gender' => '2'], $rules)->passes());   // forms send strings
        $this->assertTrue(Validator::make(['gender' => null], $rules)->passes());
        $this->assertFalse(Validator::make(['gender' => '0'], $rules)->passes());
        $this->assertFalse(Validator::make(['gender' => '3'], $rules)->passes());
    }
}
