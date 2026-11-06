<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Livewire\CompanySettings;

/**
 * Applies the current company's timezone to the app (task 2.11).
 *
 * The app then works in the company's local time: now(), today(), dates saved in the database,
 * and dates shown on screen are all Dhaka time for a Dhaka company, Nairobi time for a Nairobi company.
 * (Attendance and payroll need "today" in the company's own timezone.)
 *
 * Used by the ApplyTenantSettings middleware (web requests) and TenantAware (queued jobs).
 */
class TenantPreferences
{
    /** The app's own timezone (config/app.php), remembered before the first company's is applied. */
    private static ?string $appTimezone = null;

    public function apply(): void
    {
        self::$appTimezone ??= config()->string('app.timezone');

        $timezone = settings('timezone', CompanySettings::DEFAULTS['timezone']);

        if (is_string($timezone) && in_array($timezone, \DateTimeZone::listIdentifiers(), true)) {
            $this->useTimezone($timezone);
        }
    }

    /** Back to the app's own timezone (config/app.php), e.g. when a queue job ends. */
    public function reset(): void
    {
        if (self::$appTimezone !== null) {
            $this->useTimezone(self::$appTimezone);
        }
    }

    private function useTimezone(string $timezone): void
    {
        config(['app.timezone' => $timezone]);
        date_default_timezone_set($timezone);
    }
}
