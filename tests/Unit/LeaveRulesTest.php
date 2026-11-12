<?php

namespace Tests\Unit;

use App\Modules\Hrm\Data\LeaveCheck;
use App\Modules\Hrm\Data\LeaveRequest;
use App\Modules\Hrm\Enums\Gender;
use App\Modules\Hrm\Enums\LeaveBridging;
use App\Modules\Hrm\Enums\LeaveEligibleFrom;
use App\Modules\Hrm\Enums\Religion;
use App\Modules\Hrm\Models\LeaveType;
use App\Modules\Hrm\Services\LeaveRules;
use Carbon\CarbonImmutable;
use Tests\TestCase;

/**
 * Task 8.3: one test (or more) per leave rule. No database: the leave type is built in memory and the
 * request by hand. "Today" is Thursday 12 Nov 2026. [LV-01]
 *
 *     November 2026:  Sun 15  Mon 16  Tue 17  Wed 18  Thu 19  Fri 20  Sat 21  Sun 22
 */
class LeaveRulesTest extends TestCase
{
    private const TODAY = '2026-11-12';

    /** Friday + Saturday off around the test dates. */
    private const WEEKEND = ['2026-11-13' => true, '2026-11-14' => true, '2026-11-20' => true, '2026-11-21' => true, '2026-11-27' => true, '2026-11-28' => true];

    // ---------------------------------------------------------------- counting

    public function test_every_day_of_the_leave_counts(): void
    {
        $check = $this->check([], '2026-11-16', '2026-11-18');

        $this->assertTrue($check->ok(), implode(' ', $check->messages()));
        $this->assertSame(3.0, $check->days);
        $this->assertSame(['2026-11-16' => 1.0, '2026-11-17' => 1.0, '2026-11-18' => 1.0], $check->dates);
        $this->assertSame(3, $check->calendarDays);
    }

    public function test_weekends_inside_count_unless_the_type_excludes_them(): void
    {
        $with = ['daysOff' => self::WEEKEND];

        $this->assertSame(4.0, $this->check([], '2026-11-19', '2026-11-22', $with)->days);
        $excluded = $this->check(['exclude_holidays' => true], '2026-11-19', '2026-11-22', $with);
        $this->assertSame(2.0, $excluded->days);
        $this->assertSame(['2026-11-19', '2026-11-22'], array_keys($excluded->dates));
        $this->assertSame(4, $excluded->calendarDays);            // the span stays 4 calendar days
    }

    public function test_leave_on_days_off_only_is_refused(): void
    {
        $check = $this->check(['exclude_holidays' => true], '2026-11-20', '2026-11-21', ['daysOff' => self::WEEKEND]);

        $this->assertSame(['no_days'], $check->codes());
        $this->assertSame(0.0, $check->days);
    }

    public function test_impossible_dates_stop_the_check(): void
    {
        $check = $this->check([], '2026-11-18', '2026-11-16', ['entitlement' => null]);

        $this->assertSame(['dates'], $check->codes());               // not also "not in profile"
    }

    // ---------------------------------------------------------------- half day

    public function test_half_day(): void
    {
        $this->assertSame(['half_day'], $this->check([], '2026-11-16', null, ['halfDay' => true])->codes());

        $allowed = $this->check(['allow_half_day' => true], '2026-11-16', null, ['halfDay' => true]);
        $this->assertTrue($allowed->ok());
        $this->assertSame(0.5, $allowed->days);

        $range = $this->check(['allow_half_day' => true], '2026-11-16', '2026-11-17', ['halfDay' => true]);
        $this->assertSame(['half_day_range'], $range->codes());
    }

    // ---------------------------------------------------------------- sandwich rule

    public function test_one_sided_bridging_adds_the_days_off_next_to_the_leave(): void
    {
        // Thursday 19 off, then Friday + Saturday are days off: they become leave too.
        $check = $this->check(['bridging' => LeaveBridging::OneSided], '2026-11-19', null, ['daysOff' => self::WEEKEND]);

        $this->assertSame(3.0, $check->days);
        $this->assertSame(2, $check->bridgedDays);
        $this->assertSame(['2026-11-19', '2026-11-20', '2026-11-21'], array_keys($check->dates));

        // Sunday 22: the weekend BEFORE it is added.
        $this->assertSame(['2026-11-20', '2026-11-21', '2026-11-22'],
            array_keys($this->check(['bridging' => LeaveBridging::OneSided], '2026-11-22', null, ['daysOff' => self::WEEKEND])->dates));
    }

    public function test_one_sided_takes_the_shorter_side_and_two_sided_both(): void
    {
        // Sun 15 – Thu 19, with ONE day off before (Sat 14) and TWO after (Fri 20, Sat 21).
        $with = ['daysOff' => ['2026-11-14' => true, '2026-11-20' => true, '2026-11-21' => true]];

        $one = $this->check(['bridging' => LeaveBridging::OneSided], '2026-11-15', '2026-11-19', $with);
        $this->assertSame(6.0, $one->days);
        $this->assertArrayHasKey('2026-11-14', $one->dates);

        $two = $this->check(['bridging' => LeaveBridging::TwoSided], '2026-11-15', '2026-11-19', $with);
        $this->assertSame(8.0, $two->days);
        $this->assertSame(3, $two->bridgedDays);
    }

    public function test_no_bridging_adds_nothing(): void
    {
        $check = $this->check([], '2026-11-19', null, ['daysOff' => self::WEEKEND]);

        $this->assertSame(1.0, $check->days);
        $this->assertSame(0, $check->bridgedDays);
    }

    // ---------------------------------------------------------------- one application

    public function test_minimum_and_maximum_days(): void
    {
        $this->assertSame(['min_days'], $this->check(['min_days' => 2], '2026-11-16')->codes());
        $this->assertTrue($this->check(['min_days' => 2], '2026-11-16', '2026-11-17')->ok());

        $this->assertSame(['max_days'], $this->check(['max_days' => 2], '2026-11-16', '2026-11-18')->codes());
        $this->assertTrue($this->check(['max_days' => 3], '2026-11-16', '2026-11-18')->ok());
    }

    public function test_maximum_days_counts_the_bridged_days(): void
    {
        $check = $this->check(['max_days' => 2, 'bridging' => LeaveBridging::OneSided], '2026-11-19', null, ['daysOff' => self::WEEKEND]);

        $this->assertSame(['max_days'], $check->codes());   // 1 day asked, 3 counted
    }

    public function test_maximum_calendar_days_includes_excluded_weekends(): void
    {
        $type = ['exclude_holidays' => true, 'max_calendar_days' => 3];

        $check = $this->check($type, '2026-11-19', '2026-11-22', ['daysOff' => self::WEEKEND]);
        $this->assertSame(2.0, $check->days);
        $this->assertSame(['max_calendar_days'], $check->codes());
    }

    // ---------------------------------------------------------------- who may take it

    public function test_gender(): void
    {
        $maternity = ['gender' => Gender::Female];

        $this->assertSame(['gender'], $this->check($maternity, '2026-11-16', null, ['gender' => Gender::Male])->codes());
        $this->assertSame(['gender'], $this->check($maternity, '2026-11-16', null, ['gender' => null])->codes());   // unknown is not female
        $this->assertTrue($this->check($maternity, '2026-11-16', null, ['gender' => Gender::Female])->ok());
    }

    public function test_religion(): void
    {
        $this->assertSame(['religion'], $this->check(['religion' => Religion::Hinduism], '2026-11-16', null, ['religion' => Religion::Islam])->codes());
        $this->assertTrue($this->check(['religion' => Religion::Hinduism], '2026-11-16', null, ['religion' => Religion::Hinduism])->ok());
    }

    public function test_eligible_after_months_of_service(): void
    {
        $type = ['eligible_after_months' => 6];

        $this->assertSame(['too_early'], $this->check($type, '2026-11-16', null, ['joinDate' => CarbonImmutable::parse('2026-06-01')])->codes());
        $this->assertTrue($this->check($type, '2026-12-01', null, ['joinDate' => CarbonImmutable::parse('2026-06-01')])->ok());
    }

    public function test_eligible_from_the_confirmation_date(): void
    {
        $type = ['eligible_from' => LeaveEligibleFrom::ConfirmationDate, 'eligible_after_months' => 3];

        $this->assertSame(['not_confirmed'], $this->check($type, '2026-11-16', null, ['confirmationDate' => null])->codes());
        $this->assertSame(['too_early'], $this->check($type, '2026-11-16', null, ['confirmationDate' => CarbonImmutable::parse('2026-09-01')])->codes());
        $this->assertTrue($this->check($type, '2026-12-01', null, ['confirmationDate' => CarbonImmutable::parse('2026-09-01')])->ok());
    }

    public function test_the_type_must_be_in_the_profile(): void
    {
        $this->assertSame(['not_in_profile'], $this->check(['requires_balance' => false], '2026-11-16', null, ['entitlement' => null])->codes());
        // In the profile with 0 days is fine for leave that needs no balance (e.g. without pay).
        $this->assertTrue($this->check(['requires_balance' => false], '2026-11-16', null, ['entitlement' => 0.0, 'balance' => 0.0])->ok());
    }

    public function test_hr_only_types(): void
    {
        $this->assertSame(['hr_only'], $this->check(['hr_only' => true], '2026-11-16')->codes());
        $this->assertTrue($this->check(['hr_only' => true], '2026-11-16', null, ['byHr' => true])->ok());
    }

    public function test_times_in_the_whole_service(): void
    {
        $this->assertSame(['max_times'], $this->check(['max_times_in_service' => 2], '2026-11-16', null, ['timesTaken' => 2])->codes());
        $this->assertTrue($this->check(['max_times_in_service' => 2], '2026-11-16', null, ['timesTaken' => 1])->ok());
    }

    // ---------------------------------------------------------------- when to apply

    public function test_notice_days(): void
    {
        $type = ['notice_days' => 3];

        $this->assertSame(['notice'], $this->check($type, '2026-11-14')->codes());      // 2 days ahead
        $this->assertTrue($this->check($type, '2026-11-15')->ok());                     // 3 days ahead
        $this->assertTrue($this->check($type, '2026-11-12', null, ['byHr' => true])->ok());
    }

    public function test_post_facto(): void
    {
        $this->assertSame(['post_facto'], $this->check([], '2026-11-10')->codes());     // already started
        $this->assertTrue($this->check(['allow_post_facto' => true], '2026-11-01')->ok());   // no day limit

        $limited = ['allow_post_facto' => true, 'post_facto_days' => 2];
        $this->assertTrue($this->check($limited, '2026-11-10')->ok());                  // 2 days ago
        $this->assertSame(['post_facto_days'], $this->check($limited, '2026-11-09')->codes());
        $this->assertTrue($this->check([], '2026-11-01', null, ['byHr' => true])->ok());   // HR enters past leave
    }

    public function test_today_is_not_post_facto(): void
    {
        $this->assertTrue($this->check([], self::TODAY)->ok());
    }

    // ---------------------------------------------------------------- reason and attachment

    public function test_reason_required(): void
    {
        $this->assertSame(['reason'], $this->check(['reason_required' => true], '2026-11-16')->codes());
        $this->assertTrue($this->check(['reason_required' => true], '2026-11-16', null, ['hasReason' => true])->ok());
    }

    public function test_attachment_for_more_than_n_days(): void
    {
        $sick = ['attachment_after_days' => 2];

        $this->assertTrue($this->check($sick, '2026-11-16', '2026-11-17')->ok());                    // 2 days: not more than 2
        $this->assertSame(['attachment'], $this->check($sick, '2026-11-16', '2026-11-18')->codes()); // 3 days
        $this->assertTrue($this->check($sick, '2026-11-16', '2026-11-18', ['hasAttachment' => true])->ok());

        $this->assertSame(['attachment'], $this->check(['attachment_after_days' => 0], '2026-11-16')->codes());   // 0 = always
    }

    // ---------------------------------------------------------------- balance

    public function test_balance(): void
    {
        $this->assertSame(['balance'], $this->check([], '2026-11-16', '2026-11-18', ['balance' => 2.5])->codes());
        $this->assertTrue($this->check([], '2026-11-16', '2026-11-18', ['balance' => 3.0])->ok());
        $this->assertTrue($this->check(['requires_balance' => false], '2026-11-16', '2026-11-18', ['balance' => 0.0])->ok());
    }

    public function test_maximum_balance_caps_what_can_be_used(): void
    {
        $this->assertSame(['balance'], $this->check(['max_balance' => 2], '2026-11-16', '2026-11-18', ['balance' => 20.0])->codes());
    }

    // ---------------------------------------------------------------- employment

    public function test_employment_dates_and_overlap(): void
    {
        $this->assertSame(['before_joining'], $this->check([], '2026-11-16', null, ['joinDate' => CarbonImmutable::parse('2026-11-17')])->codes());
        $this->assertSame(['after_separation'], $this->check([], '2026-11-16', '2026-11-18', ['separationDate' => CarbonImmutable::parse('2026-11-17')])->codes());
        $this->assertSame(['overlap'], $this->check([], '2026-11-16', null, ['overlaps' => true])->codes());
    }

    public function test_all_problems_are_listed_at_once(): void
    {
        $check = $this->check(
            ['gender' => Gender::Female, 'reason_required' => true, 'notice_days' => 7],
            '2026-11-16', '2026-11-18',
            ['gender' => Gender::Male, 'balance' => 1.0],
        );

        $this->assertSame(['gender', 'notice', 'reason', 'balance'], $check->codes());
        $this->assertSame('reason', $check->problems[2]->field);
        $this->assertStringContainsString('1 days available, 3 asked', $check->messages()[3]);
    }

    /**
     * @param  array<string, mixed>  $rules  leave type columns (defaults: no limits, balance required)
     * @param  array<string, mixed>  $with  LeaveRequest arguments (defaults: in the profile, 10 days left, joined 2020)
     */
    private function check(array $rules, string $from, ?string $to = null, array $with = []): LeaveCheck
    {
        $type = new LeaveType(['code' => 'CL', 'name' => 'Casual leave', ...$rules]);

        $request = new LeaveRequest(...[
            'from' => CarbonImmutable::parse($from),
            'to' => CarbonImmutable::parse($to ?? $from),
            'today' => CarbonImmutable::parse(self::TODAY),
            'joinDate' => CarbonImmutable::parse('2020-01-01'),
            'entitlement' => 10.0,
            'balance' => 10.0,
            ...$with,
        ]);

        return (new LeaveRules)->check($type, $request);
    }
}
