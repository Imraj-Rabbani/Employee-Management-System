<?php

namespace App\Modules\Core\Livewire;

use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Setup → Company Settings. The current company's general settings, saved with the Settings service. [FND-03]
 * (Applying the timezone to the app happens in task 2.11. Who may open this page: task 2.9.)
 */
class CompanySettings extends Component
{
    /** Used until a company saves its own values. */
    public const DEFAULTS = [
        'timezone' => 'Asia/Dhaka',
        'currency' => 'BDT',
    ];

    public string $timezone = '';

    public string $currency = '';

    public function mount(): void
    {
        $this->timezone = settings('timezone', self::DEFAULTS['timezone']);
        $this->currency = settings('currency', self::DEFAULTS['currency']);
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'timezone' => ['required', 'timezone:all'],
            'currency' => ['required', Rule::in(array_keys(config()->array('currencies')))],
        ];
    }

    public function save(): void
    {
        $this->validate();

        settings()->setMany([
            'timezone' => $this->timezone,
            'currency' => $this->currency,
        ]);

        $this->dispatch('toast', message: __('Company settings saved.'), variant: 'success');
    }

    public function render(): View
    {
        $timezones = \DateTimeZone::listIdentifiers();

        $currencies = [];
        foreach (config()->array('currencies') as $code => $name) {
            $currencies[$code] = "{$code} — {$name}";
        }

        return view('core::livewire.company-settings', [
            'companyName' => tenant()?->name,
            'timezones' => array_combine($timezones, array_map(fn (string $zone) => str_replace('_', ' ', $zone), $timezones)),
            'currencies' => $currencies,
        ]);
    }
}
