<div>
    <x-ui.modal :name="$modal" size="lg" :heading="$record ? __('Edit shift') : __('New shift')">
        <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-[1fr_2fr]">
                <flux:field>
                    <flux:label>{{ __('Code') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" wire:model="form.code" />
                    <flux:error name="form.code" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('e.g. General, Morning, Night') }}</flux:description>
                    <flux:input aria-required="true" wire:model="form.name" />
                    <flux:error name="form.name" />
                </flux:field>
            </div>
            <flux:field>
                <flux:label>{{ __('Entity') }}</flux:label>
                <flux:description>{{ __('Leave empty to offer this shift everywhere.') }}</flux:description>
                <flux:select wire:model="form.entity_id">
                    <flux:select.option value="">{{ __('Whole company') }}</flux:select.option>
                    @foreach ($units as $optionValue => $optionLabel)
                        <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="form.entity_id" />
            </flux:field>

            <flux:separator :text="__('Working hours')" />
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('Start') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" type="time" wire:model="form.start_time" />
                    <flux:error name="form.start_time" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('End') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('Before the start = ends the next day.') }}</flux:description>
                    <flux:input aria-required="true" type="time" wire:model="form.end_time" />
                    <flux:error name="form.end_time" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Hours') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" type="number" step="0.25" wire:model="form.hours" />
                    <flux:error name="form.hours" />
                </flux:field>
            </div>

            <flux:separator :text="__('Late arrival')" />
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('Grace (minutes)') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('Still on time within this.') }}</flux:description>
                    <flux:input aria-required="true" type="number" wire:model="form.grace_minutes" />
                    <flux:error name="form.grace_minutes" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Grace per month') }}</flux:label>
                    <flux:description>{{ __('Times allowed. Empty = always.') }}</flux:description>
                    <flux:input type="number" wire:model="form.grace_limit_per_month" />
                    <flux:error name="form.grace_limit_per_month" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Flexibility (minutes)') }}</flux:label>
                    <flux:description>{{ __('May start later if staying later.') }}</flux:description>
                    <flux:input type="number" wire:model="form.flexibility_minutes" />
                    <flux:error name="form.flexibility_minutes" />
                </flux:field>
            </div>

            <flux:separator :text="__('Half day')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Half day below (hours)') }}</flux:label>
                    <flux:description>{{ __('Worked less than this = half day.') }}</flux:description>
                    <flux:input type="number" step="0.25" wire:model="form.half_day_hours" />
                    <flux:error name="form.half_day_hours" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Half day if in after') }}</flux:label>
                    <flux:input type="time" wire:model="form.half_day_after" />
                    <flux:error name="form.half_day_after" />
                </flux:field>
            </div>

            <flux:separator :text="__('Web punch location')" />
            <flux:switch wire:model="form.geofence_enabled" :label="__('Only punch near the office')" :description="__('Web / mobile punches must be within the radius (step 7.3).')" />
            <div x-show="$wire.form.geofence_enabled" x-cloak class="grid gap-4 sm:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('Latitude') }}</flux:label>
                    <flux:input type="number" step="any" wire:model="form.geofence_lat" />
                    <flux:error name="form.geofence_lat" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Longitude') }}</flux:label>
                    <flux:input type="number" step="any" wire:model="form.geofence_lng" />
                    <flux:error name="form.geofence_lng" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Radius (m)') }}</flux:label>
                    <flux:input type="number" wire:model="form.geofence_radius_m" />
                    <flux:error name="form.geofence_radius_m" />
                </flux:field>
            </div>

            <flux:separator />
            <flux:switch wire:model="form.is_default" :label="__('Default shift')" :description="__('Used for employees without a shift. Only one shift can be the default.')" />

            <flux:switch wire:model="form.is_active" :label="__('Active')" />

            {{-- Delete: only when editing an existing record --}}
            @if ($record)
                <flux:separator />
                <flux:button size="sm" variant="ghost" icon="trash" class="!text-red-600" x-on:click="$dispatch('confirm', {
                    title: @js(__('Delete this record?')),
                    message: @js(__('Only possible when nothing uses it any more.')),
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
