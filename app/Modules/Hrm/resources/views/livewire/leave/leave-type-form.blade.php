{{-- New / Edit leave type (LeaveTypeForm). Empty number = no limit. Switch-dependent fields only show when the switch is on. --}}
<div>
    <x-ui.modal :name="$modal" size="lg" :heading="$record ? __('Edit leave type') : __('New leave type')">
        <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-[1fr_2fr_1fr]">
                <flux:field>
                    <flux:label>{{ __('Code') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input :placeholder="__('e.g. CL, SL, EL')" aria-required="true" wire:model="form.code" />
                    <flux:error name="form.code" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input :placeholder="__('e.g. Casual leave')" aria-required="true" wire:model="form.name" />
                    <flux:error name="form.name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Order') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('In lists and reports.') }}</flux:description>
                    <flux:input aria-required="true" type="number" wire:model="form.sequence" />
                    <flux:error name="form.sequence" />
                </flux:field>
            </div>
            <flux:field>
                <flux:label>{{ __('Detail') }}</flux:label>
                <flux:textarea wire:model="form.detail" rows="2" />
                <flux:error name="form.detail" />
            </flux:field>

            <flux:separator :text="__('Who may take it')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Gender') }}</flux:label>
                    <flux:select wire:model="form.gender">
                        <flux:select.option value="">{{ __('Everyone') }}</flux:select.option>
                        @foreach ($genders as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.gender" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Religion') }}</flux:label>
                    <flux:select wire:model="form.religion">
                        <flux:select.option value="">{{ __('Everyone') }}</flux:select.option>
                        @foreach ($religions as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.religion" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Eligible after (months)') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('0 = from the first day.') }}</flux:description>
                    <flux:input aria-required="true" type="number" wire:model="form.eligible_after_months" />
                    <flux:error name="form.eligible_after_months" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Counted from') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:select aria-required="true" wire:model="form.eligible_from">
                        @foreach ($eligibleFrom as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.eligible_from" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Times in whole service') }}</flux:label>
                    <flux:input :placeholder="__('e.g. 2 for maternity leave')" type="number" wire:model="form.max_times_in_service" />
                    <flux:description>{{ __('Empty = no limit.') }}</flux:description>
                    <flux:error name="form.max_times_in_service" />
                </flux:field>
            </div>
            <flux:switch wire:model="form.hr_only" :label="__('HR only')" :description="__('Not offered in Self Service: only HR enters this leave.')" />

            <flux:separator :text="__('One application')" />
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('Minimum days') }}</flux:label>
                    <flux:description>{{ __('Empty = no minimum.') }}</flux:description>
                    <flux:input type="number" step="0.5" wire:model="form.min_days" />
                    <flux:error name="form.min_days" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Maximum days') }}</flux:label>
                    <flux:description>{{ __('Empty = no maximum.') }}</flux:description>
                    <flux:input type="number" step="0.5" wire:model="form.max_days" />
                    <flux:error name="form.max_days" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Maximum calendar days') }}</flux:label>
                    <flux:description>{{ __('From–to, weekends included.') }}</flux:description>
                    <flux:input type="number" wire:model="form.max_calendar_days" />
                    <flux:error name="form.max_calendar_days" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Notice (days)') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('Apply at least this many days before.') }}</flux:description>
                    <flux:input aria-required="true" type="number" wire:model="form.notice_days" />
                    <flux:error name="form.notice_days" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Attachment for more than (days)') }}</flux:label>
                    <flux:description>{{ __('0 = always. Empty = never.') }}</flux:description>
                    <flux:input type="number" step="0.5" wire:model="form.attachment_after_days" />
                    <flux:error name="form.attachment_after_days" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Days off next to the leave') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('Weekends / holidays touching the leave (sandwich rule).') }}</flux:description>
                    <flux:select aria-required="true" wire:model="form.bridging">
                        @foreach ($bridgings as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.bridging" />
                </flux:field>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 items-start">
                <flux:switch wire:model="form.allow_half_day" :label="__('Half day allowed')" />

                <flux:switch wire:model="form.exclude_holidays" :label="__('Do not count weekends and holidays')" :description="__('Only working days inside the leave are taken.')" />
                <flux:switch wire:model="form.reason_required" :label="__('Reason required')" />
                <div class="space-y-3">
                    <flux:switch wire:model="form.allow_post_facto" :label="__('May apply afterwards')" :description="__('After the leave has started (post facto).')" />
                    <div x-show="$wire.form.allow_post_facto" x-cloak>
                        <flux:field>
                            <flux:label>{{ __('Up to days after') }}</flux:label>
                            <flux:description>{{ __('Empty = any time.') }}</flux:description>
                            <flux:input type="number" wire:model="form.post_facto_days" />
                            <flux:error name="form.post_facto_days" />
                        </flux:field>
                    </div>
                </div>
            </div>

            <flux:separator :text="__('Balance and earning')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Earned') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('The days per year come from the leave profile.') }}</flux:description>
                    <flux:select aria-required="true" wire:model="form.earn_type">
                        @foreach ($earnTypes as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.earn_type" />
                </flux:field>
                <div x-show="$wire.form.earn_type == {{ $perDaysWorked }}" x-cloak>
                    <flux:field>
                        <flux:label>{{ __('Days worked per earned day') }}</flux:label>
                        <flux:input :placeholder="__('e.g. 18')" type="number" step="0.01" wire:model="form.earn_per_days_worked" />
                        <flux:error name="form.earn_per_days_worked" />
                    </flux:field>
                </div>
                <flux:field>
                    <flux:label>{{ __('Maximum balance') }}</flux:label>
                    <flux:description>{{ __('No more than this can be available. Empty = no limit.') }}</flux:description>
                    <flux:input type="number" step="0.5" wire:model="form.max_balance" />
                    <flux:error name="form.max_balance" />
                </flux:field>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 items-start">
                <flux:switch wire:model="form.requires_balance" :label="__('Needs a balance')" :description="__('Cannot be taken beyond the days left.')" />

                <flux:switch wire:model="form.is_lwp" :label="__('Without pay')" :description="__('Payroll deducts these days (LWP).')" />
            </div>

            <flux:separator :text="__('Year end and encashment')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <div class="space-y-3">
                    <flux:switch wire:model="form.can_carry_forward" :label="__('Carry forward')" :description="__('Unused days move to the next year.')" />
                    <div x-show="$wire.form.can_carry_forward" x-cloak class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('At most (days)') }}</flux:label>
                            <flux:description>{{ __('Empty = all.') }}</flux:description>
                            <flux:input type="number" step="0.5" wire:model="form.carry_forward_limit" />
                            <flux:error name="form.carry_forward_limit" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Expire after (months)') }}</flux:label>
                            <flux:description>{{ __('Empty = never.') }}</flux:description>
                            <flux:input type="number" wire:model="form.carry_forward_expiry_months" />
                            <flux:error name="form.carry_forward_expiry_months" />
                        </flux:field>
                    </div>
                </div>
                <div class="space-y-3">
                    <flux:switch wire:model="form.is_encashable" :label="__('Encashable')" :description="__('Unused days can be paid out.')" />
                    <div x-show="$wire.form.is_encashable" x-cloak>
                        <flux:field>
                            <flux:label>{{ __('At most per encashment (days)') }}</flux:label>
                            <flux:description>{{ __('Empty = all.') }}</flux:description>
                            <flux:input type="number" step="0.5" wire:model="form.encash_limit" />
                            <flux:error name="form.encash_limit" />
                        </flux:field>
                    </div>
                </div>
            </div>

            <flux:separator />
            <flux:switch wire:model="form.is_active" :label="__('Active')" />

            {{-- Delete: only when editing an existing record --}}
            @if ($record)
                <flux:separator />
                <flux:button size="sm" variant="ghost" icon="trash" class="!text-red-600" x-on:click="$dispatch('confirm', {
                    title: @js(__('Delete this record?')),
                    message: @js(__('Not possible while a leave profile contains it. Make it inactive instead.')),
                    confirmText: @js(__('Delete')),
                    onConfirm: () => $wire.delete(),
                })">{{ __('Delete') }}</flux:button>
            @endif
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="{{ $modal }}" variant="primary">{{ __('Save') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
