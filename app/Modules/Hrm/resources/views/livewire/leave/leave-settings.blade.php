<div class="glass overflow-hidden rounded-2xl">
    <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
        <div>
            <flux:heading size="lg">{{ __('Leave year and earning') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Balances, carry forward and limits count per leave year. Now: :year.', ['year' => $current->label()]) }}</flux:text>
        </div>
    </div>

    <div class="p-6">
        <form wire:submit="save" class="flex flex-wrap items-end gap-6">
            <flux:field>
                <flux:label>{{ __('Year starts in') }}</flux:label>
                <flux:description>{{ $locked ? __('Fixed: leave balances already exist.') : __('January = the calendar year.') }}</flux:description>
                <flux:select wire:model="startMonth" class="w-44" :disabled="$locked">
                    @foreach ($months as $optionValue => $optionLabel)
                        <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="startMonth" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Earn leave from') }}</flux:label>
                <flux:description>{{ __('Go-live date: earlier periods are in the opening balances.') }}</flux:description>
                <flux:input type="date" wire:model="earnFrom" class="w-44" />
                <flux:error name="earnFrom" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Close the year after (days)') }}</flux:label>
                <flux:description>{{ __('Then carry forward / expire.') }}</flux:description>
                <flux:input type="number" min="0" max="90" wire:model="closeAfterDays" class="w-36" />
                <flux:error name="closeAfterDays" />
            </flux:field>
            <flux:button type="submit" size="sm" variant="primary">{{ __('Save') }}</flux:button>
        </form>
    </div>
</div>
