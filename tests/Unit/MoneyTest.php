<?php

namespace Tests\Unit;

use App\Core\Casts\MoneyCast;
use App\Core\Support\Money;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

/** Task 9.1: Money is exact (no floats), immutable, and round-trips through a DECIMAL column. */
class MoneyTest extends TestCase
{
    public function test_it_adds_without_float_errors(): void
    {
        $this->assertSame('0.30', Money::of('0.1')->add(Money::of('0.2'))->toDecimal());
    }

    public function test_it_parses_strings_ints_and_commas(): void
    {
        $this->assertSame(125050, Money::of('1,250.50')->minor());
        $this->assertSame(500000, Money::of(5000)->minor());
        $this->assertTrue(Money::of(null)->isZero());
        $this->assertSame('-3.50', Money::of('-3.5')->toDecimal());
    }

    public function test_it_rejects_junk(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Money::of('abc');
    }

    public function test_percent_rounds_half_up(): void
    {
        $this->assertSame('22750.00', Money::of(50000)->percent('45.5')->toDecimal());
        $this->assertSame('0.01', Money::of('0.05')->percent('10')->toDecimal());   // 0.005 → 0.01
        $this->assertSame('33.33', Money::of(100)->divide(3)->toDecimal());
    }

    public function test_multiply_and_subtract(): void
    {
        $this->assertSame('2500.00', Money::of(1000)->multiply('2.5')->toDecimal());
        $this->assertSame('-50.00', Money::of(100)->subtract(Money::of(150))->toDecimal());
    }

    public function test_allocate_never_loses_a_cent(): void
    {
        $parts = Money::of('100.00')->allocate([1, 1, 1]);

        $this->assertSame(['33.34', '33.33', '33.33'], array_map(fn (Money $m) => $m->toDecimal(), $parts));
        $this->assertSame(10000, array_sum(array_map(fn (Money $m) => $m->minor(), $parts)));
    }

    public function test_comparison_and_sign(): void
    {
        $this->assertTrue(Money::of('1.00')->equals(Money::of(1)));
        $this->assertSame(-1, Money::of(1)->compare(Money::of(2)));
        $this->assertTrue(Money::of(-1)->isNegative());
        $this->assertTrue(Money::of(-1)->negate()->isPositive());
        $this->assertSame('5.00', Money::of(-5)->abs()->toDecimal());
    }

    public function test_it_is_immutable(): void
    {
        $a = Money::of(10);
        $a->add(Money::of(5));

        $this->assertSame('10.00', $a->toDecimal());
    }

    public function test_format_for_screens(): void
    {
        $this->assertSame('1,250.50', Money::of('1250.5')->format());
        $this->assertSame('-0.05', Money::of('-0.05')->format());
        $this->assertSame('9,999,999,999,999.99', Money::of('9999999999999.99')->format());   // largest DECIMAL(15,2)
    }

    public function test_cast_round_trips_a_decimal_column(): void
    {
        $cast = new MoneyCast;
        $model = new class extends Model {};

        $money = $cast->get($model, 'basic', '50000.00', []);
        $this->assertSame(5000000, $money->minor());
        $this->assertSame('50000.00', $cast->set($model, 'basic', $money, []));
        $this->assertSame('12.50', $cast->set($model, 'basic', '12.5', []));
        $this->assertNull($cast->get($model, 'basic', null, []));
        $this->assertNull($cast->set($model, 'basic', null, []));
    }
}
