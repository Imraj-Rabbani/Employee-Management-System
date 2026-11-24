<?php

namespace App\Modules\Core\Livewire;

use App\Core\Support\PlanLimits;
use Illuminate\Contracts\View\View;
use Illuminate\Validation\Rule;
use Livewire\Component;

/**
 * Global Config → Company settings. The current company's general settings, saved with the Settings service.
 * Fiscal year and currency are plain text: whatever is typed is what the app shows (e.g. "2025-2026", "BDT").
 * The fiscal year is shown in the top bar (layouts/app/sidebar.blade.php).
 * Also shows the company's plan and how much of its limits is used (PlanLimits), read-only.
 * The timezone is applied by the ApplyTenantSettings middleware; who may open this page: the route's can: rule.
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

    public string $fiscalYear = '';

    public string $dateFormat = '';

    public function mount(): void
    {
        $this->timezone = settings('timezone', self::DEFAULTS['timezone']);
        $this->currency = settings('currency', self::DEFAULTS['currency']);
        $this->fiscalYear = self::fiscalYear();
        $this->dateFormat = settings('date_format', self::DEFAULTS['date_format']);
    }

    /**
     * The company's fiscal year as saved, e.g. "2025-2026". Until one is saved: the July–June year
     * that contains today (the Bangladesh fiscal year), e.g. in November 2026 "2026-2027".
     */
    public static function fiscalYear(): string
    {
        $saved = (string) settings('fiscal_year', '');
        if ($saved !== '') {
            return $saved;
        }
        $start = now()->month >= 7 ? now()->year : now()->year - 1;

        return $start.'-'.($start + 1);
    }

    /** @return array<string, mixed> */
    protected function rules(): array
    {
        return [
            'timezone' => ['required', 'timezone:all'],
            'currency' => ['required', 'string', 'max:20'],
            'fiscalYear' => ['required', 'string', 'max:50'],
            'dateFormat' => ['required', Rule::in(self::DATE_FORMATS)],
        ];
    }

    public function save(): void
    {
        $this->validate();

        settings()->setMany([
            'timezone' => $this->timezone,
            'currency' => trim($this->currency),
            'fiscal_year' => trim($this->fiscalYear),
            'date_format' => $this->dateFormat,
        ]);

        // Reload the page, so the top bar shows the new fiscal year at once.
        session()->flash('toast', ['message' => __('Company settings saved.'), 'variant' => 'success']);
        $this->redirect(route('core.settings'), navigate: true);
    }

    public function render(): View
    {
        $timezones = \DateTimeZone::listIdentifiers();

        $limits = app(PlanLimits::class);
        $plan = tenant()->plan;

        return view('core::livewire.company-settings', [
            'companyName' => tenant()?->name,
            'plan' => $plan,
            'employeesUsed' => $limits->employeeCount(),
            'storageUsed' => $limits->storageUsed(),
            'timezones' => array_combine($timezones, array_map(fn (string $zone) => str_replace('_', ' ', $zone), $timezones)),
            // e.g. "d/m/Y" => "05/11/2026"
            'dateFormats' => collect(self::DATE_FORMATS)->mapWithKeys(fn (string $format) => [$format => now()->format($format)])->all(),
        ]);
    }
}
