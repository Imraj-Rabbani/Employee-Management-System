<?php

namespace App\Core\Enums;

/**
 * For backed enums that have a label() method. Gives every such enum the same helpers:
 *
 *     Gender::options()           [1 => 'Male', 2 => 'Female']   for <x-form.select :options="...">
 *     Gender::labelFor(2)         'Female'  (null / unknown value → '')
 *     Gender::values()            [1, 2]    for validation: Rule::in(Gender::values()) or Rule::enum(Gender::class)
 *
 * A trait in an enum works like in a class: the methods become part of the enum.
 */
trait HasOptions
{
    /** @return array<int|string, string> value => translated label, in declaration order */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }

    public static function labelFor(int|string|null $value): string
    {
        return $value === null ? '' : (self::tryFrom($value)?->label() ?? '');
    }

    /** @return list<int|string> */
    public static function values(): array
    {
        return array_map(fn (self $case) => $case->value, self::cases());
    }
}
