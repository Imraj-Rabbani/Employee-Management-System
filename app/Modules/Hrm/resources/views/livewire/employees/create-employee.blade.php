<div>
    <x-ui.modal name="employee-create-form" size="lg" :heading="__('New employee')"
        :subheading="__('The basics only. You fill in the rest on the profile, which opens after saving.')">
        <form wire:submit="save" id="employee-create-form" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-[1fr_2fr_2fr]">
                <flux:field>
                    <flux:label>{{ __('Employee code') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" wire:model="form.code" />
                    <flux:error name="form.code" />
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
            <div class="grid gap-4 sm:grid-cols-[1fr_2fr_2fr]">
                <flux:field>
                    <flux:label>{{ __('Title') }}</flux:label>
                    <flux:description>{{ __('Mr, Ms, Dr') }}</flux:description>
                    <flux:input wire:model="form.title" />
                    <flux:error name="form.title" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Name (local)') }}</flux:label>
                    <flux:input wire:model="form.name_local" />
                    <flux:error name="form.name_local" />
                </flux:field>

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
            </div>

            <flux:separator :text="__('Job')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Join date') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input type="date" aria-required="true" wire:model="form.join_date" />
                    <flux:error name="form.join_date" />
                </flux:field>

                {{-- One dropdown per entity type (Department, Branch ...). --}}
                @foreach ($choices as $typeId => $choice)
                    <flux:field wire:key="entity-type-{{ $typeId }}">
                        <flux:label>
                            {{ $choice['name'] }}
                            @if ($choice['required'])
                                <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span>
                            @endif
                        </flux:label>
                        <flux:select :aria-required="$choice['required'] ? 'true' : 'false'" wire:model="form.entities.{{ $typeId }}">
                            <flux:select.option value="">{{ __('None') }}</flux:select.option>
                            @foreach ($choice['options'] as $optionValue => $optionLabel)
                                <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="form.entities.{{ $typeId }}" />
                    </flux:field>
                @endforeach

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
                    <flux:label>{{ __('Employment type') }}</flux:label>
                    <flux:select wire:model="form.employment_type_id">
                        <flux:select.option value="">{{ __('None') }}</flux:select.option>
                        @foreach ($types as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.employment_type_id" />
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
            </div>
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="employee-create-form" variant="primary">{{ __('Add employee') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
