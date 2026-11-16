{{-- Encash leave (EncashmentForm): HR or Self Service. The money is worked out by payroll. --}}
@php $num = fn ($v) => \App\Modules\Hrm\Models\LeaveApplication::daysLabel($v); @endphp
<div>
    <x-ui.modal name="encashment-form" :heading="$encashmentId ? __('Change the encashment and send it again') : __('Encash leave')"
        :subheading="__('Unused days of :year are paid out with the next payroll once approved.', ['year' => $year->label()])">
        <form wire:submit="save" id="encashment-form" class="space-y-4">
            @unless ($self)
                <flux:field>
                    <flux:label>{{ __('Employee') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:select aria-required="true" wire:model.live="employeeId" :disabled="$encashmentId !== null">
                        <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                        @foreach ($employees as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="employeeId" />
                </flux:field>
            @endunless
            <flux:field>
                <flux:label>{{ __('Leave type') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:select aria-required="true" wire:model.live="leaveTypeId">
                    <flux:select.option value="">{{ $types === [] && $employeeId !== '' ? __('No encashable leave') : __('Choose…') }}</flux:select.option>
                    @foreach ($types as $optionValue => $optionLabel)
                        <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="leaveTypeId" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Days') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                @if ($balance !== null)
                    <flux:description>{{ __(':n days left', ['n' => $num($balance)]).($limit ? ' · '.__('at most :n at a time', ['n' => $num($limit)]) : '') }}</flux:description>
                @endif
                <flux:input aria-required="true" type="number" step="0.5" min="0.5" wire:model="days" class="w-36" />
                <flux:error name="days" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Reason') }}</flux:label>
                <flux:textarea wire:model="reason" rows="2" />
                <flux:error name="reason" />
            </flux:field>
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="encashment-form" variant="primary" icon="paper-airplane">{{ __('Send for approval') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
