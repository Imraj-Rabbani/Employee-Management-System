<?php

namespace App\Modules\Core\Services;

use App\Modules\Core\Models\Setting;
use Illuminate\Support\Facades\Cache;
use LogicException;

/**
 * The current company's settings (legacy `config` table with autoload).
 *
 *     settings('timezone', 'Asia/Dhaka')                 read, with a default
 *     settings()->set('timezone', 'Asia/Dhaka')           write
 *     settings()->set('report.logo', $path, autoload: false)   rarely used, big, or secret values
 *     settings()->forget('timezone')                     delete
 *
 * Autoload settings are read with ONE query the first time, then kept in the cache
 * (key "tenant.{id}.settings") until a setting changes. Non-autoload settings are read on demand.
 *
 * One instance per request/job ("scoped" in CoreServiceProvider), like TenantManager.
 */
class Settings
{
    /** @var array<string, mixed>|null autoload settings of this request, null = not loaded yet */
    private ?array $loaded = null;

    /** @var array<string, mixed> non-autoload settings read during this request */
    private array $extra = [];

    public function get(string $key, mixed $default = null): mixed
    {
        $loaded = $this->autoloaded();

        if (array_key_exists($key, $loaded)) {
            return $loaded[$key];
        }

        if (! array_key_exists($key, $this->extra)) {
            $setting = Setting::where('key', $key)->first();
            $this->extra[$key] = $setting?->value;

            if ($setting === null) {
                return $default;
            }
        }

        return $this->extra[$key] ?? $default;
    }

    public function has(string $key): bool
    {
        return array_key_exists($key, $this->autoloaded()) || Setting::where('key', $key)->exists();
    }

    public function set(string $key, mixed $value, bool $autoload = true): void
    {
        Setting::updateOrCreate(['key' => $key], ['value' => $value, 'autoload' => $autoload]);

        $this->flush();
    }

    /** @param  array<string, mixed>  $values  several settings at once, e.g. from a settings form */
    public function setMany(array $values): void
    {
        foreach ($values as $key => $value) {
            Setting::updateOrCreate(['key' => $key], ['value' => $value]);
        }

        $this->flush();
    }

    public function forget(string $key): void
    {
        Setting::where('key', $key)->delete();

        $this->flush();
    }

    /** @return array<string, mixed> all autoload settings */
    public function all(): array
    {
        return $this->autoloaded();
    }

    /** Drop the cached copy, so the next read comes fresh from the database. */
    public function flush(): void
    {
        Cache::forget($this->cacheKey());
        $this->loaded = null;
        $this->extra = [];
    }

    /** @return array<string, mixed> */
    private function autoloaded(): array
    {
        /** @var array<string, mixed> */
        return $this->loaded ??= Cache::rememberForever(
            $this->cacheKey(),
            fn () => Setting::where('autoload', true)->pluck('value', 'key')->all(),
        );
    }

    /** One cache entry per company: the cache table is shared by all of them. */
    private function cacheKey(): string
    {
        $tenant = tenant() ?? throw new LogicException('Settings were used with no tenant active.');

        return "tenant.{$tenant->id}.settings";
    }
}
