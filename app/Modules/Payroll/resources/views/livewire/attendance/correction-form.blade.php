{{-- Request a correction (CorrectionForm): HR or Self Service. --}}
<div>
    <x-ui.modal name="correction-form" :heading="$correctionId ? __('Change the correction and send it again') : __('Request a correction')"
        :subheading="__('The times replace the punches of that day once the correction is approved. Leave a time empty to keep the punch.')">
        <form wire:submit="save" id="correction-form" class="space-y-4">
            @unless ($self)
                <flux:field>
                    <flux:label>{{ __('Employee') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:select aria-required="true" wire:model.live="employeeId" :disabled="$correctionId !== null">
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
                <flux:input type="date" aria-required="true" wire:model.live="date" max="{{ $today }}" :disabled="$correctionId !== null" />
                <flux:error name="date" />
            </flux:field>

            @if ($day)
                <flux:text class="text-sm">
                    {{ __('Processed: :status, in :in, out :out', [
                        'status' => $day->status->label(),
                        'in' => $day->in_at?->format('H:i') ?? '—',
                        'out' => $day->out_at?->format('H:i') ?? '—',
                    ]) }}@if ($day->shift) · {{ __('shift :code', ['code' => $day->shift->code]) }}@endif
                </flux:text>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('In') }}</flux:label>
                    <flux:input wire:model="in" type="time" />
                    <flux:error name="in" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Out') }}</flux:label>
                    <flux:description>{{ __('Earlier than In = the next morning.') }}</flux:description>
                    <flux:input wire:model="out" type="time" />
                    <flux:error name="out" />
                </flux:field>
            </div>
            <flux:field>
                <flux:label>{{ __('Reason') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:textarea aria-required="true" rows="3" wire:model="reason" />
                <flux:error name="reason" />
            </flux:field>
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="correction-form" variant="primary" icon="paper-airplane">{{ __('Send for approval') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
