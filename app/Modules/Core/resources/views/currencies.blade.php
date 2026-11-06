<x-layouts.app :title="__('Currencies')">
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-4">
            <flux:subheading>{{ __('Currencies with their rate against the company\'s default currency (:code).', ['code' => \App\Modules\Core\Models\Currency::defaultCode()]) }}</flux:subheading>
            <flux:button variant="primary" icon="plus" x-on:click="$dispatch('create-currency')">{{ __('New currency') }}</flux:button>
        </div>

        <livewire:core::currency-table />
        <livewire:core::currency-form />
    </div>
</x-layouts.app>
