<div>
    <x-ui.modal name="roster-form" :heading="$roster ? __('Edit roster :no', ['no' => $roster->number()]) : __('New roster')">
        <form wire:submit="save" id="roster-form" class="space-y-6">
            <x-form.select wire:model="form.org_unit_id" :label="__('Org unit')" :options="$units" required
                :hint="__('The employees of this unit (and the units below it) appear in the grid.')" />
            <x-form.input wire:model="form.title" :label="__('Title')" required :hint="__('e.g. December 2026')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.date wire:model="form.from_date" :label="__('From')" required />
                <x-form.date wire:model="form.to_date" :label="__('To')" required
                    :hint="$roster ? __('Shortening the period removes the shifts outside it.') : __('At most :n days.', ['n' => \App\Modules\Payroll\Livewire\Attendance\RosterForm::MAX_DAYS])" />
            </div>
            <x-form.textarea wire:model="form.note" :label="__('Note')" rows="2" />

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
