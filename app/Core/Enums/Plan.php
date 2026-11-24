<?php

namespace App\Core\Enums;

/**
 * The pricing plan a company is on (tenants.plan). Each plan has limits:
 *
 *     $tenant->plan->maxEmployees()     10 / 100   employee records, the founder included
 *     $tenant->plan->storageBytes()     5 GB / 50 GB of uploaded files
 *
 * Chosen on the sign-up page; the platform admin can change it (Edit company).
 * The checks themselves live in App\Core\Support\PlanLimits.
 */
enum Plan: string
{
    use HasOptions;

    case Free = 'free';
    case Business = 'business';

    public function label(): string
    {
        return match ($this) {
            self::Free => __('Free'),
            self::Business => __('Business'),
        };
    }

    public function maxEmployees(): int
    {
        return match ($this) {
            self::Free => 10,
            self::Business => 100,
        };
    }

    public function storageGb(): int
    {
        return match ($this) {
            self::Free => 5,
            self::Business => 50,
        };
    }

    public function storageBytes(): int
    {
        return $this->storageGb() * 1024 ** 3;
    }

    /** One line for the sign-up page / admin, e.g. "Up to 10 employees · 5 GB storage". */
    public function summary(): string
    {
        return __('Up to :employees employees · :gb GB storage', ['employees' => $this->maxEmployees(), 'gb' => $this->storageGb()]);
    }
}
