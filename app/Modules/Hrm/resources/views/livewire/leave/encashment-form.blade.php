{{-- Encash leave (EncashmentForm): HR or Self Service. The money is worked out by payroll. --}}
@php $num = fn ($v) => \App\Modules\Hrm\Models\LeaveApplication::daysLabel($v); @endphp
<div>
    <x-ui.modal name="encashment-form" :heading="$encashmentId ? __('Change the encashment and send it again') : __('Encash leave')"
        :subheading="__('Unused days of :year are paid out with the next payroll once approved.', ['year' => $year->label()])">
        <form wire:submit="save" id="encashment-form" class="space-y-4">
            @unless ($self)
                <x-form.select wire:model.live="employeeId" :label="__('Employee')" :options="$employees" :placeholder="__('Choose…')" required
                               :disabled="$encashmentId !== null" />
            @endunless
            <x-form.select wire:model.live="leaveTypeId" :label="__('Leave type')" :options="$types" required
                :placeholder="$types === [] && $employeeId !== '' ? __('No encashable leave') : __('Choose…')" />
            <x-form.input type="number" step="0.5" min="0.5" wire:model="days" class="w-36" :label="__('Days')" required
                :hint="$balance === null ? null : __(':n days left', ['n' => $num($balance)]).($limit ? ' · '.__('at most :n at a time', ['n' => $num($limit)]) : '')" />
            <x-form.textarea wire:model="reason" :label="__('Reason')" rows="2" />
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="encashment-form" variant="primary" icon="paper-airplane">{{ __('Send for approval') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
