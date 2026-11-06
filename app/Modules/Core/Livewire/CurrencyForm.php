<?php

namespace App\Modules\Core\Livewire;

use App\Core\Livewire\LookupForm;
use App\Modules\Core\Models\Currency;
use Illuminate\Validation\Rule;

/**
 * New / Edit / Delete currency (modal "currency-form"). [ORG-01]
 * Everything except the fields comes from LookupForm.
 */
class CurrencyForm extends LookupForm
{
    protected function key(): string
    {
        return 'currency';
    }

    protected function model(): string
    {
        return Currency::class;
    }

    protected function defaults(): array
    {
        return ['code' => '', 'name' => '', 'symbol' => '', 'exchange_rate' => '1', 'is_active' => true];
    }

    protected function fieldRules(): array
    {
        return [
            'code' => ['required', 'string', 'size:3', 'alpha', Rule::unique(Currency::class, 'code')->ignore($this->recordId)],
            'name' => ['required', 'string', 'max:100'],
            'symbol' => ['nullable', 'string', 'max:10'],
            'exchange_rate' => ['required', 'numeric', 'gt:0', 'max:999999999'],
            'is_active' => ['boolean'],
        ];
    }

    protected function labels(): array
    {
        return ['exchange_rate' => __('exchange rate')];
    }

    /** Codes are stored in capitals; the default currency always has rate 1. */
    protected function check(array $values): bool
    {
        $this->form['code'] = strtoupper((string) $values['code']);

        if ($this->form['code'] === Currency::defaultCode() && (float) $values['exchange_rate'] !== 1.0) {
            $this->addError('form.exchange_rate', __('The company\'s default currency always has rate 1.'));

            return false;
        }

        return true;
    }

    public function save(): void
    {
        $this->form['code'] = strtoupper((string) ($this->form['code'] ?? ''));
        parent::save();
    }

    protected function view(): string
    {
        return 'core::livewire.currency-form';
    }
}
