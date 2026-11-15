<?php

namespace App\Core\Support;

use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use InvalidArgumentException;
use Stringable;

/**
 * An amount of money, kept as a whole number of minor units (2 decimals: 1250 = 12.50). Never a float,
 * so 0.1 + 0.2 is exactly 0.3. Immutable: every method returns a new Money. Matches DECIMAL(15,2) columns.
 *
 *     Money::of('1250.50')                 from a string / int / decimal column value
 *     $basic->percent('45.5')              45.5 % of it, rounded half up to 2 decimals
 *     $gross->subtract($tax)->format()     "1,250.50"
 *     $total->allocate([1, 1, 1])          split without losing a cent
 */
final readonly class Money implements Stringable
{
    private const SCALE = 2;

    private function __construct(private int $minor) {}

    public static function zero(): self
    {
        return new self(0);
    }

    public static function ofMinor(int $minor): self
    {
        return new self($minor);
    }

    /** From a decimal string or int. More than 2 decimals are rounded half up (use a Rounding rule before if you need another). */
    public static function of(string|int|null $amount): self
    {
        if ($amount === null || $amount === '') {
            return self::zero();
        }

        $amount = is_string($amount) ? str_replace(',', '', trim($amount)) : (string) $amount;

        if (! is_numeric($amount)) {
            throw new InvalidArgumentException("Not a money amount: '{$amount}'.");
        }

        return self::fromDecimal(BigDecimal::of($amount));
    }

    public function add(self $other): self
    {
        return new self($this->minor + $other->minor);
    }

    public function subtract(self $other): self
    {
        return new self($this->minor - $other->minor);
    }

    /** Multiply by a rate or count given as a string (e.g. '0.5', '1.25'), rounded half up. */
    public function multiply(string|int $factor, RoundingMode $mode = RoundingMode::HalfUp): self
    {
        return self::fromDecimal($this->decimal()->multipliedBy($factor), $mode);
    }

    /** $percent of this amount: Money::of(50000)->percent('45.5') = 22750.00 */
    public function percent(string|int $percent, RoundingMode $mode = RoundingMode::HalfUp): self
    {
        return self::fromDecimal($this->decimal()->multipliedBy($percent)->dividedBy(100, 6, RoundingMode::HalfUp), $mode);
    }

    /** Divide by a number (e.g. days in month for a daily rate), rounded half up. */
    public function divide(string|int $divisor, RoundingMode $mode = RoundingMode::HalfUp): self
    {
        return self::fromDecimal($this->decimal()->dividedBy($divisor, 6, RoundingMode::HalfUp), $mode);
    }

    /**
     * Split by ratios; the parts always add up to the original (leftover minor units go to the first parts).
     *
     * @param  non-empty-list<int>  $ratios
     * @return list<self>
     */
    public function allocate(array $ratios): array
    {
        $total = array_sum($ratios);
        if ($ratios === [] || $total <= 0) {
            throw new InvalidArgumentException('Ratios must be a non-empty list with a positive sum.');
        }

        $parts = array_map(fn (int $r) => intdiv($this->minor * $r, $total), $ratios);
        $left = $this->minor - array_sum($parts);

        for ($i = 0; $left !== 0; $i = ($i + 1) % count($parts)) {
            $step = $left > 0 ? 1 : -1;
            $parts[$i] += $step;
            $left -= $step;
        }

        return array_map(fn (int $m) => new self($m), $parts);
    }

    public function negate(): self
    {
        return new self(-$this->minor);
    }

    public function abs(): self
    {
        return new self(abs($this->minor));
    }

    public function equals(self $other): bool
    {
        return $this->minor === $other->minor;
    }

    public function compare(self $other): int
    {
        return $this->minor <=> $other->minor;
    }

    public function isZero(): bool
    {
        return $this->minor === 0;
    }

    public function isNegative(): bool
    {
        return $this->minor < 0;
    }

    public function isPositive(): bool
    {
        return $this->minor > 0;
    }

    public function minor(): int
    {
        return $this->minor;
    }

    /** Plain decimal string for the database: "1250.50" */
    public function toDecimal(): string
    {
        return (string) $this->decimal();
    }

    /** For screens: "1,250.50" */
    public function format(): string
    {
        return number_format($this->minor / 100, self::SCALE);
    }

    public function __toString(): string
    {
        return $this->toDecimal();
    }

    private function decimal(): BigDecimal
    {
        return BigDecimal::ofUnscaledValue($this->minor, self::SCALE);
    }

    private static function fromDecimal(BigDecimal $value, RoundingMode $mode = RoundingMode::HalfUp): self
    {
        return new self($value->toScale(self::SCALE, $mode)->getUnscaledValue()->toInt());
    }
}
