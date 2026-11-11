{{-- Ask for overtime (OvertimeForm): HR / manager or Self Service. --}}
<div>
    <x-ui.modal name="overtime-form" :heading="$requestId ? __('Change the request and send it again') : __('Ask for overtime')"
        :subheading="__('Approvers may change the hours. Only approved hours are paid.')">
        <form wire:submit="save" id="overtime-form" class="space-y-4">
            @unless ($self)
                <x-form.select wire:model.live="employeeId" :label="__('Employee')" :options="$employees" :placeholder="__('Choose…')" required
                               :disabled="$requestId !== null" />
            @endunless
            <x-form.date wire:model.live="date" :label="__('Day')" required :disabled="$requestId !== null" />

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

            <x-form.input wire:model="hours" type="number" step="0.25" min="0.25" max="24" class="w-36" :label="__('Hours')" required
                          :hint="__('In quarter hours, e.g. 2.5 = 2 h 30 min.')" />
            <x-form.textarea wire:model="detail" :label="__('Work done')" required />
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="overtime-form" variant="primary" icon="paper-airplane">{{ __('Send for approval') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
