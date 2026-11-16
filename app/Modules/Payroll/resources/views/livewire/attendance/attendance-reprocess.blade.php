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
                <flux:field>
                    <flux:label>{{ __('From') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input type="date" aria-required="true" wire:model="from" max="{{ $today }}" />
                    <flux:error name="from" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('To') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input type="date" aria-required="true" wire:model="to" max="{{ $today }}" />
                    <flux:error name="to" />
                </flux:field>
            </div>
            <flux:field>
                <flux:label>{{ __('Org unit') }}</flux:label>
                <flux:description>{{ __('Also the units below it.') }}</flux:description>
                <flux:select wire:model="orgUnitId">
                    <flux:select.option value="">{{ __('All') }}</flux:select.option>
                    @foreach ($units as $optionValue => $optionLabel)
                        <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="orgUnitId" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Employee') }}</flux:label>
                <flux:select wire:model="employeeId">
                    <flux:select.option value="">{{ __('All') }}</flux:select.option>
                    @foreach ($employees as $optionValue => $optionLabel)
                        <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="employeeId" />
            </flux:field>
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="attendance-reprocess-form" variant="primary" icon="arrow-path">{{ __('Process') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
