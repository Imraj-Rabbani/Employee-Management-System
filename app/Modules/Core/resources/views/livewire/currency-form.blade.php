<x-form.lookup-modal :$modal :$record :heading="$record ? __('Edit currency') : __('New currency')">
        <div class="grid gap-4 sm:grid-cols-[1fr_2fr_1fr]">
            <x-form.input wire:model="form.code" :label="__('Code')" required maxlength="3" :hint="__('e.g. USD')" />
            <x-form.input wire:model="form.name" :label="__('Name')" required />
            <x-form.input wire:model="form.symbol" :label="__('Symbol')" />
        </div>
        <x-form.input wire:model="form.exchange_rate" type="number" step="0.000001" min="0" :label="__('Exchange rate')" required
            :hint="__('1 unit of this currency = this many units of the default currency (:code).', ['code' => \App\Modules\Core\Models\Currency::defaultCode()])" />
        <x-form.toggle wire:model="form.is_active" :label="__('Active')" />
</x-form.lookup-modal>
