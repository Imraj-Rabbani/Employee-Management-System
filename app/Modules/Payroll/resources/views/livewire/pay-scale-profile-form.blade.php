{{-- New / Edit pay scale profile (PayScaleProfileForm). The payroll run follows these rules. --}}
<div>
    <x-ui.modal :name="$modal" :heading="$record ? __('Edit pay scale profile') : __('New pay scale profile')">
        <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('Code') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" wire:model="form.code" />
                    <flux:error name="form.code" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input :placeholder="__('e.g. Regular staff, Workers')" aria-required="true" wire:model="form.name" class="sm:col-span-2" />
                    <flux:error name="form.name" />
                </flux:field>
            </div>

            <flux:switch wire:model="form.pays_arrear" :label="__('Pay arrears')" :description="__('Back-dated salary changes, and the days of late joiners, are paid in a later run.')" />

            <flux:field>
                <flux:label>{{ __('Threshold day') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:description>{{ __('Who joins after this day of the month is first paid in the next month\'s run. 0 = no threshold.') }}</flux:description>
                <flux:input aria-required="true" wire:model="form.threshold_day" type="number" min="0" max="28" />
                <flux:error name="form.threshold_day" />
            </flux:field>

            <flux:switch wire:model.live="form.deduct_lwp" :label="__('Deduct leave without pay')" />
            @if ($form['deduct_lwp'] ?? true)
                <flux:switch wire:model="form.ignore_late_lwp" :label="__('Except LWP from late attendance')" :description="__('LWP that comes from lates (e.g. 3 lates = 1 day) is not deducted.')" />
            @endif

            <flux:switch wire:model="form.deduct_absent" :label="__('Deduct absent days')" :description="__('Absent days from attendance reduce the salary; a half day counts as half. Switch off for staff who do not punch.')" />

            <flux:field>
                <flux:label>{{ __('Detail') }}</flux:label>
                <flux:textarea wire:model="form.detail" rows="2" />
                <flux:error name="form.detail" />
            </flux:field>

            <flux:switch wire:model="form.is_active" :label="__('Active')" />

            {{-- Delete: only when editing an existing record --}}
            @if ($record)
                <flux:separator />
                <flux:button size="sm" variant="ghost" icon="trash" class="!text-red-600" x-on:click="$dispatch('confirm', {
                    title: @js(__('Delete this record?')),
                    message: @js(__('Not possible while employees use it. Make it inactive instead.')),
                    confirmText: @js(__('Delete')),
                    onConfirm: () => $wire.delete(),
                })">{{ __('Delete') }}</flux:button>
            @endif
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="{{ $modal }}" variant="primary">{{ __('Save') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
