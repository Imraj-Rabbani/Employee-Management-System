<div>
    <x-ui.modal :name="$modal" size="lg" :heading="__('Edit job details')">
        <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Employee code') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" wire:model="form.code" />
                    <flux:error name="form.code" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Attendance ID') }}</flux:label>
                    <flux:description>{{ __('The ID on the attendance device.') }}</flux:description>
                    <flux:input wire:model="form.access_code" />
                    <flux:error name="form.access_code" />
                </flux:field>
            </div>

            <flux:separator :text="__('Placement')" />
            <flux:text class="text-sm">{{ __('The entity is changed on the History tab, so every move is kept with its date.') }}</flux:text>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Designation') }}</flux:label>
                    <flux:select wire:model="form.designation_id">
                        <flux:select.option value="">{{ __('None') }}</flux:select.option>
                        @foreach ($designations as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.designation_id" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Grade') }}</flux:label>
                    <flux:select wire:model="form.grade_id">
                        <flux:select.option value="">{{ __('None') }}</flux:select.option>
                        @foreach ($grades as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.grade_id" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Position') }}</flux:label>
                    <flux:select wire:model="form.position_id">
                        <flux:select.option value="">{{ __('None') }}</flux:select.option>
                        @foreach ($positions as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.position_id" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Employment type') }}</flux:label>
                    <flux:select wire:model="form.employment_type_id">
                        <flux:select.option value="">{{ __('None') }}</flux:select.option>
                        @foreach ($types as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.employment_type_id" />
                </flux:field>
            </div>

            <flux:separator :text="__('Reports to')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Supervisor') }}</flux:label>
                    <flux:select wire:model="form.supervisor_id">
                        <flux:select.option value="">{{ __('Nobody') }}</flux:select.option>
                        @foreach ($people as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.supervisor_id" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Dotted-line manager') }}</flux:label>
                    <flux:select wire:model="form.dotted_line_manager_id">
                        <flux:select.option value="">{{ __('Nobody') }}</flux:select.option>
                        @foreach ($people as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.dotted_line_manager_id" />
                </flux:field>
            </div>

            <flux:separator :text="__('Dates & pay')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Join date') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input type="date" aria-required="true" wire:model="form.join_date" />
                    <flux:error name="form.join_date" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Planned confirmation') }}</flux:label>
                    <flux:input type="date" wire:model="form.planned_confirmation_date" />
                    <flux:error name="form.planned_confirmation_date" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Confirmed on') }}</flux:label>
                    <flux:input type="date" wire:model="form.confirmation_date" />
                    <flux:error name="form.confirmation_date" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Payment method') }}</flux:label>
                    <flux:select wire:model="form.payment_method">
                        <flux:select.option value="">{{ __('Not set') }}</flux:select.option>
                        @foreach ($paymentMethods as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.payment_method" />
                </flux:field>
            </div>

            <flux:separator :text="__('Attendance')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Shift') }}</flux:label>
                    <flux:select wire:model="form.shift_id">
                        <flux:select.option value="">{{ $defaultShift ? __('Default (:shift)', ['shift' => $defaultShift]) : __('Default shift') }}</flux:select.option>
                        @foreach ($shifts as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.shift_id" />
                </flux:field>

                <flux:switch wire:model="form.has_shifting" :label="__('Works on a roster')" :description="__('Shift planned day by day on a roster (Attendance → Rosters).')" />
                <flux:field>
                    <flux:label>{{ __('Weekend profile') }}</flux:label>
                    <flux:select wire:model="form.weekend_profile_id">
                        <flux:select.option value="">{{ __('None') }}</flux:select.option>
                        @foreach ($weekendProfiles as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.weekend_profile_id" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Holiday calendar') }}</flux:label>
                    <flux:select wire:model="form.holiday_profile_id">
                        <flux:select.option value="">{{ __('None') }}</flux:select.option>
                        @foreach ($holidayProfiles as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.holiday_profile_id" />
                </flux:field>
            </div>

            <flux:separator :text="__('Leave')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Leave profile') }}</flux:label>
                    <flux:description>{{ __('Which leave this employee may take, and how many days.') }}</flux:description>
                    <flux:select wire:model="form.leave_profile_id">
                        <flux:select.option value="">{{ __('None') }}</flux:select.option>
                        @foreach ($leaveProfiles as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.leave_profile_id" />
                </flux:field>
            </div>
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="{{ $modal }}" variant="primary">{{ __('Save') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
