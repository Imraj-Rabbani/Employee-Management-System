{{-- Apply for leave (LeaveApplicationForm): HR or Self Service. The box under the dates is the live LeaveRules result. --}}
@php $num = fn ($v) => \App\Modules\Hrm\Models\LeaveApplication::daysLabel($v); @endphp
<div>
    <x-ui.modal name="leave-form" size="lg" :heading="$applicationId ? __('Change the application and send it again') : __('Apply for leave')"
        :subheading="$self ? __('Your approvers are asked in turn. The days are taken from your balance once approved.') : __('For an employee you can see. Notice and post-facto limits do not apply to HR.')">
        <form wire:submit="save" id="leave-form" class="space-y-4">
            @unless ($self)
                <x-form.select wire:model.live="employeeId" :label="__('Employee')" :options="$employees" :placeholder="__('Choose…')" required
                               :disabled="$applicationId !== null" />
            @endunless

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.select wire:model.live="leaveTypeId" :label="__('Leave type')" :options="$types" required
                    :placeholder="$types === [] && $employeeId !== '' ? __('No leave profile: ask HR') : __('Choose…')" />
                <div class="flex items-end pb-2">
                    <x-form.toggle wire:model.live="isHalfDay" :label="__('Half day')" />
                </div>
                <x-form.date wire:model.live="fromDate" :label="$isHalfDay ? __('Day') : __('First day')" required />
                @unless ($isHalfDay)
                    <x-form.date wire:model.live="toDate" :label="__('Last day')" required />
                @endunless
            </div>

            @if ($preview)
                <div @class([
                    'rounded-lg px-4 py-3 text-sm ring-1 ring-inset',
                    'bg-green-500/10 text-green-800 ring-green-500/20 dark:text-green-200' => $preview->ok(),
                    'bg-amber-500/10 text-amber-800 ring-amber-500/25 dark:text-amber-200' => ! $preview->ok(),
                ])>
                    <p class="font-medium">
                        {{ __(':days days', ['days' => $num($preview->days)]) }}
                        @if ($balance !== null && $leaveType?->requires_balance)
                            · {{ __(':left left before, :after after', ['left' => $num($balance), 'after' => $num($balance - $preview->days)]) }}
                        @endif
                        @if ($preview->bridgedDays > 0)
                            · {{ __('incl. :n days off next to the leave', ['n' => $preview->bridgedDays]) }}
                        @endif
                    </p>
                    @foreach ($preview->messages() as $message)
                        <p>• {{ $message }}</p>
                    @endforeach
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.select wire:model="delegateeId" :label="__('Delegatee')" :options="$delegatees" :placeholder="__('Nobody')"
                    :hint="__('Who does the work meanwhile.')" />
                <x-form.input wire:model="contact" :label="__('Contact during leave')" :hint="__('Phone or address.')" />
            </div>
            <x-form.textarea wire:model.blur="reason" :label="__('Reason')" rows="2" :required="(bool) $leaveType?->reason_required" />

            <div class="space-y-2">
                @if ($existing?->attachment_path)
                    <flux:text class="text-sm">{{ __('Current attachment: :name', ['name' => $existing->attachment_name]) }}</flux:text>
                @endif
                <x-form.file wire:model.live="upload" :label="$existing?->attachment_path ? __('Replace attachment') : __('Attachment')"
                    accept=".pdf,.jpg,.jpeg,.png,.docx" :hint="__('e.g. a medical certificate. PDF, JPG, PNG or Word, max 5 MB.')" />
            </div>
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="leave-form" variant="primary" icon="paper-airplane">{{ __('Send for approval') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
