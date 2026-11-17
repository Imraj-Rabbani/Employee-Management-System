<div>
    <x-ui.modal name="roster-form" :heading="$roster ? __('Edit roster :no', ['no' => $roster->number()]) : __('New roster')">
        <form wire:submit="save" id="roster-form" class="space-y-6">
            <flux:field>
                <flux:label>{{ __('Entity') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:description>{{ __('The employees currently in this entity appear in the grid.') }}</flux:description>
                <flux:select aria-required="true" wire:model="form.entity_id">
                    @foreach ($units as $optionValue => $optionLabel)
                        <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.entity_id" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Title') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:description>{{ __('e.g. December 2026') }}</flux:description>
                <flux:input aria-required="true" wire:model="form.title" />
                <flux:error name="form.title" />
            </flux:field>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('From') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input type="date" aria-required="true" wire:model="form.from_date" />
                    <flux:error name="form.from_date" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('To') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ $roster ? __('Shortening the period removes the shifts outside it.') : __('At most :n days.', ['n' => \App\Modules\Payroll\Livewire\Attendance\RosterForm::MAX_DAYS]) }}</flux:description>
                    <flux:input type="date" aria-required="true" wire:model="form.to_date" />
                    <flux:error name="form.to_date" />
                </flux:field>
            </div>
            <flux:field>
                <flux:label>{{ __('Note') }}</flux:label>
                <flux:textarea wire:model="form.note" rows="2" />
                <flux:error name="form.note" />
            </flux:field>

            @if ($roster?->isDraft())
                <flux:separator />
                <flux:button size="sm" variant="ghost" icon="trash" class="!text-red-600" x-on:click="$dispatch('confirm', {
                    title: @js(__('Delete this roster?')),
                    message: @js(__('Its planned shifts are deleted too.')),
                    confirmText: @js(__('Delete')),
                    onConfirm: () => $wire.delete(),
                })">{{ __('Delete') }}</flux:button>
            @endif
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="roster-form" variant="primary">{{ $roster ? __('Save') : __('Create and open') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
