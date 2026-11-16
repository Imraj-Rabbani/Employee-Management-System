{{-- Apply for leave (LeaveApplicationForm): HR or Self Service. The box under the dates is the live LeaveRules result. --}}
@php $num = fn ($v) => \App\Modules\Hrm\Models\LeaveApplication::daysLabel($v); @endphp
<div>
    <x-ui.modal name="leave-form" size="lg" :heading="$applicationId ? __('Change the application and send it again') : __('Apply for leave')"
        :subheading="$self ? __('Your approvers are asked in turn. The days are taken from your balance once approved.') : __('For an employee you can see. Notice and post-facto limits do not apply to HR.')">
        <form wire:submit="save" id="leave-form" class="space-y-4">
            @unless ($self)
                <flux:field>
                    <flux:label>{{ __('Employee') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:select aria-required="true" wire:model.live="employeeId" :disabled="$applicationId !== null">
                        <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                        @foreach ($employees as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="employeeId" />
                </flux:field>
            @endunless

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Leave type') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:select aria-required="true" wire:model.live="leaveTypeId">
                        <flux:select.option value="">{{ $types === [] && $employeeId !== '' ? __('No leave profile: ask HR') : __('Choose…') }}</flux:select.option>
                        @foreach ($types as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="leaveTypeId" />
                </flux:field>
                <div class="flex items-end pb-2">
                    <flux:switch wire:model.live="isHalfDay" :label="__('Half day')" />
                </div>
                <flux:field>
                    <flux:label>{{ $isHalfDay ? __('Day') : __('First day') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input type="date" aria-required="true" wire:model.live="fromDate" />
                    <flux:error name="fromDate" />
                </flux:field>
                @unless ($isHalfDay)
                    <flux:field>
                        <flux:label>{{ __('Last day') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                        <flux:input type="date" aria-required="true" wire:model.live="toDate" />
                        <flux:error name="toDate" />
                    </flux:field>
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
                <flux:field>
                    <flux:label>{{ __('Delegatee') }}</flux:label>
                    <flux:description>{{ __('Who does the work meanwhile.') }}</flux:description>
                    <flux:select wire:model="delegateeId">
                        <flux:select.option value="">{{ __('Nobody') }}</flux:select.option>
                        @foreach ($delegatees as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="delegateeId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Contact during leave') }}</flux:label>
                    <flux:description>{{ __('Phone or address.') }}</flux:description>
                    <flux:input wire:model="contact" />
                    <flux:error name="contact" />
                </flux:field>
            </div>
            <flux:field>
                <flux:label>{{ __('Reason') }} @if ((bool) $leaveType?->reason_required)<span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span>@endif</flux:label>
                <flux:textarea :aria-required="((bool) $leaveType?->reason_required) ? 'true' : null" wire:model.blur="reason" rows="2" />
                <flux:error name="reason" />
            </flux:field>

            <div class="space-y-2">
                @if ($existing?->attachment_path)
                    <flux:text class="text-sm">{{ __('Current attachment: :name', ['name' => $existing->attachment_name]) }}</flux:text>
                @endif
                <flux:field>
                    <flux:label>{{ $existing?->attachment_path ? __('Replace attachment') : __('Attachment') }}</flux:label>
                    <flux:description>{{ __('e.g. a medical certificate. PDF, JPG, PNG or Word, max 5 MB.') }}</flux:description>
                    <flux:input type="file" wire:model.live="upload" accept=".pdf,.jpg,.jpeg,.png,.docx" />
                    <flux:text wire:loading wire:target="upload" class="mt-2 text-sm">{{ __('Uploading…') }}</flux:text>
                    <flux:error name="upload" />
                </flux:field>
            </div>
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="leave-form" variant="primary" icon="paper-airplane">{{ __('Send for approval') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
