<div>
    <x-ui.modal :name="$modal" size="lg" :heading="__('Edit personal details')">
        <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-[1fr_2fr_2fr]">
                <flux:field>
                    <flux:label>{{ __('Title') }}</flux:label>
                    <flux:description>{{ __('Mr, Ms, Dr') }}</flux:description>
                    <flux:input wire:model="form.title" />
                    <flux:error name="form.title" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('First name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" wire:model="form.first_name" />
                    <flux:error name="form.first_name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Last name') }}</flux:label>
                    <flux:input wire:model="form.last_name" />
                    <flux:error name="form.last_name" />
                </flux:field>
            </div>
            <flux:field>
                <flux:label>{{ __('Name (local)') }}</flux:label>
                <flux:input :placeholder="__('e.g. the name in Bangla')" wire:model="form.name_local" />
                <flux:error name="form.name_local" />
            </flux:field>

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('Gender') }}</flux:label>
                    <flux:select wire:model="form.gender">
                        <flux:select.option value="">{{ __('Not set') }}</flux:select.option>
                        @foreach ($genders as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.gender" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Date of birth') }}</flux:label>
                    <flux:input type="date" wire:model="form.birth_date" />
                    <flux:error name="form.birth_date" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Marital status') }}</flux:label>
                    <flux:select wire:model="form.marital_status">
                        <flux:select.option value="">{{ __('Not set') }}</flux:select.option>
                        @foreach ($maritalStatuses as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.marital_status" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Blood group') }}</flux:label>
                    <flux:select wire:model="form.blood_group">
                        <flux:select.option value="">{{ __('Not set') }}</flux:select.option>
                        @foreach ($bloodGroups as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.blood_group" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Religion') }}</flux:label>
                    <flux:select wire:model="form.religion">
                        <flux:select.option value="">{{ __('Not set') }}</flux:select.option>
                        @foreach ($religions as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.religion" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Nationality') }}</flux:label>
                    <flux:input wire:model="form.nationality" />
                    <flux:error name="form.nationality" />
                </flux:field>
            </div>

            <flux:separator :text="__('Identity')" />
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('National ID') }}</flux:label>
                    <flux:input wire:model="form.national_id" />
                    <flux:error name="form.national_id" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('TIN') }}</flux:label>
                    <flux:input wire:model="form.tin" />
                    <flux:error name="form.tin" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Passport no.') }}</flux:label>
                    <flux:input wire:model="form.passport_no" />
                    <flux:error name="form.passport_no" />
                </flux:field>
            </div>

            <flux:separator :text="__('Contact')" />
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('Mobile') }}</flux:label>
                    <flux:input wire:model="form.mobile" type="tel" />
                    <flux:error name="form.mobile" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Personal email') }}</flux:label>
                    <flux:input wire:model="form.personal_email" type="email" />
                    <flux:error name="form.personal_email" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Office email') }}</flux:label>
                    <flux:input wire:model="form.office_email" type="email" />
                    <flux:error name="form.office_email" />
                </flux:field>
            </div>
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="{{ $modal }}" variant="primary">{{ __('Save') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
