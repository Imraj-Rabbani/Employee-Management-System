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
        'date_format' => 'd M Y',
    ];

    /** PHP date formats a company can choose (format => example is built in render()). */
    public const DATE_FORMATS = ['d M Y', 'd/m/Y', 'm/d/Y', 'Y-m-d', 'd.m.Y'];

    public string $timezone = '';

    public string $currency = '';

    public string $dateFormat = '';

    public function mount(): void
    {
        $this->timezone = settings('timezone', self::DEFAULTS['timezone']);
        $this->currency = settings('currency', self::DEFAULTS['currency']);
        $this->dateFormat = settings('date_format', self::DEFAULTS['date_format']);
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'timezone' => ['required', 'timezone:all'],
            'currency' => ['required', Rule::in(array_keys(config()->array('currencies')))],
            'dateFormat' => ['required', Rule::in(self::DATE_FORMATS)],
        ];
    }

    public function save(): void
    {
        $this->validate();

        settings()->setMany([
            'timezone' => $this->timezone,
            'currency' => $this->currency,
            'date_format' => $this->dateFormat,
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
            // e.g. "d/m/Y" => "05/11/2026"
            'dateFormats' => collect(self::DATE_FORMATS)->mapWithKeys(fn (string $format) => [$format => now()->format($format)])->all(),
        ]);
    }
}
