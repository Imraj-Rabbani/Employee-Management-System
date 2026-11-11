<div>
    <flux:modal.trigger name="attendance-reprocess">
        <flux:button size="sm" variant="primary" icon="arrow-path">{{ __('Process again') }}</flux:button>
    </flux:modal.trigger>

    <x-ui.modal name="attendance-reprocess" :heading="__('Process attendance again')">
        <form wire:submit="run" id="attendance-reprocess-form" class="space-y-4">
            <flux:text class="text-sm">
                {{ __('Works out these days again from punches, rosters, shifts and holidays. Needed after changing a shift, a weekend profile or an employee\'s calendar. Locked days (closed payroll) are not changed.') }}
            </flux:text>

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.date wire:model="from" :label="__('From')" required max="{{ $today }}" />
                <x-form.date wire:model="to" :label="__('To')" required max="{{ $today }}" />
            </div>
            <x-form.select wire:model="orgUnitId" :label="__('Org unit')" :options="$units" :placeholder="__('All')"
                           :hint="__('Also the units below it.')" />
            <x-form.select wire:model="employeeId" :label="__('Employee')" :options="$employees" :placeholder="__('All')" />
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="attendance-reprocess-form" variant="primary" icon="arrow-path">{{ __('Process') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
