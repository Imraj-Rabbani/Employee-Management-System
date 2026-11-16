{{-- Ask for overtime (OvertimeForm): HR / manager or Self Service. --}}
<div>
    <x-ui.modal name="overtime-form" :heading="$requestId ? __('Change the request and send it again') : __('Ask for overtime')"
        :subheading="__('Approvers may change the hours. Only approved hours are paid.')">
        <form wire:submit="save" id="overtime-form" class="space-y-4">
            @unless ($self)
                <flux:field>
                    <flux:label>{{ __('Employee') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:select aria-required="true" wire:model.live="employeeId" :disabled="$requestId !== null">
                        <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                        @foreach ($employees as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="employeeId" />
                </flux:field>
            @endunless
            <flux:field>
                <flux:label>{{ __('Day') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:input type="date" aria-required="true" wire:model.live="date" :disabled="$requestId !== null" />
                <flux:error name="date" />
            </flux:field>

            @if ($day)
                <flux:text class="text-sm">
                    {{ __('Processed: :status, in :in, out :out, overtime :ot', [
                        'status' => $day->status->label(),
                        'in' => $day->in_at?->format('H:i') ?? '—',
                        'out' => $day->out_at?->format('H:i') ?? '—',
                        'ot' => \App\Modules\Payroll\Models\AttendanceDay::hoursLabel($day->ot_minutes),
                    ]) }}
                </flux:text>
            @endif

            <flux:field>
                <flux:label>{{ __('Hours') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:description>{{ __('In quarter hours, e.g. 2.5 = 2 h 30 min.') }}</flux:description>
                <flux:input aria-required="true" wire:model="hours" type="number" step="0.25" min="0.25" max="24" class="w-36" />
                <flux:error name="hours" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Work done') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:textarea aria-required="true" rows="3" wire:model="detail" />
                <flux:error name="detail" />
            </flux:field>
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="overtime-form" variant="primary" icon="paper-airplane">{{ __('Send for approval') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
