<?php

use App\Core\Tenancy\TenantManager;
use App\Models\Tenant;
use App\Modules\Core\Livewire\CompanySettings;
use App\Modules\Core\Services\Settings;
use Illuminate\Support\Carbon;

/*
| Global helper functions. Loaded by Composer ("files" in composer.json).
*/

if (! function_exists('tenant')) {
    /** The current tenant (company), or null when none is active, e.g. on the login page. */
    function tenant(): ?Tenant
    {
        return app(TenantManager::class)->current();
    }
}

if (! function_exists('settings')) {
    /**
     * The current company's settings.
     *   settings()                       the Settings service (->set(), ->forget(), ->all())
     *   settings('timezone', 'UTC')      one value, with a default
     */
    function settings(?string $key = null, mixed $default = null): mixed
    {
        $settings = app(Settings::class);

        return $key === null ? $settings : $settings->get($key, $default);
    }
}

if (! function_exists('format_date')) {
    /**
     * A date in the company's date format (Setup → Company Settings), e.g. "05 Nov 2026".
     * Use this in every screen instead of ->format('d M Y'). Empty → "".
     */
    function format_date(DateTimeInterface|string|null $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        return Carbon::parse($date)->format(date_format_setting());
    }
}

if (! function_exists('format_datetime')) {
    /** Date and time in the company's date format and timezone, e.g. "05 Nov 2026, 14:30". */
    function format_datetime(DateTimeInterface|string|null $date): string
    {
        if ($date === null || $date === '') {
            return '';
        }

        return Carbon::parse($date)->setTimezone(config()->string('app.timezone'))->format(date_format_setting().', H:i');
    }
}

if (! function_exists('date_format_setting')) {
    /** The company's date format, or the default when no company is active (e.g. Platform admin). */
    function date_format_setting(): string
    {
        $default = CompanySettings::DEFAULTS['date_format'];

        if (tenant() === null) {
            return $default;
        }

        $format = settings('date_format', $default);

        return is_string($format) ? $format : $default;
    }
}
