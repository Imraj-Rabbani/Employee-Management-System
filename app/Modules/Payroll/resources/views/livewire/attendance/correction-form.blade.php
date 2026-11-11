{{-- Request a correction (CorrectionForm): HR or Self Service. --}}
<div>
    <x-ui.modal name="correction-form" :heading="$correctionId ? __('Change the correction and send it again') : __('Request a correction')"
        :subheading="__('The times replace the punches of that day once the correction is approved. Leave a time empty to keep the punch.')">
        <form wire:submit="save" id="correction-form" class="space-y-4">
            @unless ($self)
                <x-form.select wire:model.live="employeeId" :label="__('Employee')" :options="$employees" :placeholder="__('Choose…')" required
                               :disabled="$correctionId !== null" />
            @endunless
            <x-form.date wire:model.live="date" :label="__('Day')" required max="{{ $today }}" :disabled="$correctionId !== null" />

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
                <x-form.input wire:model="in" type="time" :label="__('In')" />
                <x-form.input wire:model="out" type="time" :label="__('Out')" :hint="__('Earlier than In = the next morning.')" />
            </div>
            <x-form.textarea wire:model="reason" :label="__('Reason')" required />
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="correction-form" variant="primary" icon="paper-airplane">{{ __('Send for approval') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
