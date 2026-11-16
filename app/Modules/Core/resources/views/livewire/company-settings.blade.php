<div class="max-w-2xl">
    <div class="glass overflow-hidden rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ $companyName }}</flux:heading>
                <flux:text class="mt-1">{{ __('General settings for your company.') }}</flux:text>
            </div>
        </div>

        <div class="p-6">
            <form wire:submit="save" id="company-settings-form" class="space-y-6">
                <flux:field>
                    <flux:label>{{ __('Timezone') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('Dates and times in the app are shown in this timezone.') }}</flux:description>
                    <flux:select aria-required="true" wire:model="timezone">
                        @foreach ($timezones as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="timezone" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Date format') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('How dates are shown everywhere in the app.') }}</flux:description>
                    <flux:select aria-required="true" wire:model="dateFormat">
                        @foreach ($dateFormats as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="dateFormat" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Default currency') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('Used for salaries and amounts unless another currency is chosen.') }}</flux:description>
                    <flux:select aria-required="true" wire:model="currency">
                        @foreach ($currencies as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="currency" />
                </flux:field>
            </form>
        </div>

        <div class="border-t border-white/50 px-6 py-4 text-sm text-zinc-600 dark:border-white/10 dark:text-zinc-300">
            <div class="flex justify-end">
                <flux:button type="submit" form="company-settings-form" variant="primary">
                    <span wire:loading.remove wire:target="save">{{ __('Save') }}</span>
                    <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
                </flux:button>
            </div>
        </div>
    </div>
</div>
