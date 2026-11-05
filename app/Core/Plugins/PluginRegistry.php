<?php

namespace App\Core\Plugins;

use App\Models\Tenant;
use Illuminate\Validation\Rule;

/**
 * Per-company customisation (Architecture §8).
 *
 * A module declares "slots" (extension points) and the plugins that can fill them:
 *
 *     $registry->slot('tax', 'Tax calculator', module: 'payroll');
 *     $registry->add('tax', 'TaxBangladesh', 'Bangladesh', BangladeshTaxCalculator::class);
 *
 * The super admin picks a plugin per slot for each company (Platform admin → Edit company).
 * The choice is saved in tenants.plugins, e.g. {"tax": "TaxBangladesh", "payroll_stages": ["AIRASTRA"]}.
 *
 * Code then asks for the class to use for the current company:
 *
 *     $this->app->bind(TaxCalculator::class, fn () => app(PluginRegistry::class)->resolve('tax', FlatSlabTaxCalculator::class));
 *
 * One shared instance (singleton): registered in AppServiceProvider, filled by module providers.
 */
class PluginRegistry
{
    /** @var array<string, array{label: string, multiple: bool, module: string|null}> */
    private array $slots = [];

    /** @var array<string, array<string, array{label: string, class: class-string}>> slot => plugin key => plugin */
    private array $plugins = [];

    /** Declare an extension point. $multiple: the company may pick several (e.g. extra payroll stages). */
    public function slot(string $key, string $label, bool $multiple = false, ?string $module = null): void
    {
        $this->slots[$key] = ['label' => $label, 'multiple' => $multiple, 'module' => $module];
        $this->plugins[$key] ??= [];
    }

    /** @param  class-string  $class */
    public function add(string $slot, string $key, string $label, string $class): void
    {
        $this->plugins[$slot][$key] = ['label' => $label, 'class' => $class];
    }

    /** @return array<string, array{label: string, multiple: bool, module: string|null}> */
    public function slots(): array
    {
        return $this->slots;
    }

    /** @return array<string, string> plugin key => label, for a dropdown */
    public function options(string $slot): array
    {
        return array_map(fn (array $plugin) => $plugin['label'], $this->plugins[$slot] ?? []);
    }

    /**
     * The object to use for a single-choice slot: the company's plugin, or $default when it chose none.
     *
     * @template T of object
     *
     * @param  class-string<T>  $default
     * @return T
     */
    public function resolve(string $slot, string $default, ?Tenant $tenant = null): object
    {
        $choice = ($tenant ?? tenant())?->plugins[$slot] ?? null;
        $class = is_string($choice) ? ($this->plugins[$slot][$choice]['class'] ?? $default) : $default;

        /** @var T */
        return app($class);
    }

    /**
     * The objects for a multiple-choice slot, in the order they were chosen. Empty when none.
     *
     * @return list<object>
     */
    public function resolveMany(string $slot, ?Tenant $tenant = null): array
    {
        $choices = ($tenant ?? tenant())?->plugins[$slot] ?? [];

        return array_values(array_filter(array_map(
            fn (mixed $key) => is_string($key) && isset($this->plugins[$slot][$key]) ? app($this->plugins[$slot][$key]['class']) : null,
            is_array($choices) ? $choices : [],
        )));
    }

    /**
     * Validation rules for a form field "plugins" holding [slot => key] or [slot => [keys]].
     *
     * @return array<string, mixed>
     */
    public function rules(string $field = 'plugins'): array
    {
        $rules = [$field => ['array']];

        foreach ($this->slots as $key => $slot) {
            $allowed = Rule::in(array_keys($this->plugins[$key] ?? []));

            if ($slot['multiple']) {
                $rules["{$field}.{$key}"] = ['array'];
                $rules["{$field}.{$key}.*"] = [$allowed];
            } else {
                $rules["{$field}.{$key}"] = ['nullable', 'string', $allowed];
            }
        }

        return $rules;
    }

    /**
     * Clean form input before saving: keep known slots only, drop empty choices.
     *
     * @param  array<string, mixed>  $input
     * @return array<string, string|list<string>>
     */
    public function clean(array $input): array
    {
        $clean = [];

        foreach ($this->slots as $key => $slot) {
            $value = $input[$key] ?? null;

            if ($slot['multiple'] && is_array($value) && $value !== []) {
                $clean[$key] = array_values(array_map('strval', $value));
            } elseif (! $slot['multiple'] && is_string($value) && $value !== '') {
                $clean[$key] = $value;
            }
        }

        return $clean;
    }
}
