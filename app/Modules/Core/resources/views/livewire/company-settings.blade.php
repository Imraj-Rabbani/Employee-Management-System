<div class="max-w-2xl">
    <x-ui.card :heading="$companyName" :subheading="__('General settings for your company.')">
        <form wire:submit="save" id="company-settings-form" class="space-y-6">
            <x-form.select wire:model="timezone" :label="__('Timezone')" :options="$timezones" :hint="__('Dates and times in the app are shown in this timezone.')" required />
            <x-form.select wire:model="dateFormat" :label="__('Date format')" :options="$dateFormats" :hint="__('How dates are shown everywhere in the app.')" required />
            <x-form.select wire:model="currency" :label="__('Default currency')" :options="$currencies" :hint="__('Used for salaries and amounts unless another currency is chosen.')" required />
        </form>

        <x-slot:footer>
            <div class="flex justify-end">
                <flux:button type="submit" form="company-settings-form" variant="primary">
                    <span wire:loading.remove wire:target="save">{{ __('Save') }}</span>
                    <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
                </flux:button>
            </div>
        </x-slot:footer>
    </x-ui.card>
</div>
