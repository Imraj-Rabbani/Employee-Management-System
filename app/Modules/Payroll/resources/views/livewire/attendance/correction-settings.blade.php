<div class="glass overflow-hidden rounded-2xl">
    <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
        <div>
            <flux:heading size="lg">{{ __('Limits for employees (Self Service)') }}</flux:heading>
            <flux:text class="mt-1">{{ __('0 = no limit. Corrections asked by HR are not limited.') }}</flux:text>
        </div>
    </div>

    <div class="p-6">
        <form wire:submit="save" class="flex flex-wrap items-end gap-6">
            <flux:field>
                <flux:label>{{ __('Days back') }}</flux:label>
                <flux:description>{{ __('How old a day may be.') }}</flux:description>
                <flux:input wire:model="daysBack" type="number" min="0" max="366" class="w-28" />
                <flux:error name="daysBack" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Per month') }}</flux:label>
                <flux:description>{{ __('Rejected ones do not count.') }}</flux:description>
                <flux:input wire:model="monthlyLimit" type="number" min="0" max="31" class="w-28" />
                <flux:error name="monthlyLimit" />
            </flux:field>
            <flux:button type="submit" size="sm" variant="primary">{{ __('Save') }}</flux:button>
        </form>
    </div>
</div>
