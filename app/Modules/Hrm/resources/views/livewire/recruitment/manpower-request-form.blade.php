<div>
    <x-ui.modal name="manpower-request-form" size="lg"
        :heading="$request ? __('Edit :no', ['no' => $request->number()]) : __('New manpower request')"
        :subheading="$editable ? __('Saved as a draft. Send it for approval from its page.') : __('Waiting for approval or already answered: it can no longer be changed.')">
        <form wire:submit="save" id="manpower-request-form" class="space-y-6">
            <fieldset @disabled(! $editable) class="space-y-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Entity') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                        <flux:select aria-required="true" wire:model.live="form.entity_id">
                            <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                            @foreach ($units as $optionValue => $optionLabel)
                                <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="form.entity_id" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Designation') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                        <flux:select aria-required="true" wire:model="form.designation_id">
                            <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                            @foreach ($designations as $optionValue => $optionLabel)
                                <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="form.designation_id" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Position') }}</flux:label>
                        <flux:description>{{ __('Optional: the seat in the entity') }}</flux:description>
                        <flux:select wire:model="form.position_id">
                            <flux:select.option value="">{{ __('None') }}</flux:select.option>
                            @foreach ($positions as $optionValue => $optionLabel)
                                <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="form.position_id" />
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
                        <flux:label>{{ __('Headcount') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                        <flux:input aria-required="true" wire:model="form.headcount" type="number" min="1" />
                        <flux:error name="form.headcount" />
                    </flux:field>
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Type') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                        <flux:select aria-required="true" wire:model.live="form.type">
                            @foreach ($requestTypes as $optionValue => $optionLabel)
                                <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="form.type" />
                    </flux:field>
                    @if (($form['type'] ?? '') ===\App\Modules\Hrm\Enums\ManpowerRequestType::Replacement->value)
                        <flux:field>
                            <flux:label>{{ __('Replaces') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                            <flux:select aria-required="true" wire:model="form.replaces_employee_id">
                                <flux:select.option value="">{{ $employees ? __('Choose…') : __('Choose the entity first') }}</flux:select.option>
                                @foreach ($employees as $optionValue => $optionLabel)
                                    <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="form.replaces_employee_id" />
                        </flux:field>
                    @endif
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:field>
                        <flux:label>{{ __('Salary from') }}</flux:label>
                        <flux:description>{{ __('Monthly gross') }}</flux:description>
                        <flux:input wire:model="form.salary_min" type="number" min="0" step="0.01" />
                        <flux:error name="form.salary_min" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Salary to') }}</flux:label>
                        <flux:input wire:model="form.salary_max" type="number" min="0" step="0.01" />
                        <flux:error name="form.salary_max" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Needed by') }}</flux:label>
                        <flux:input type="date" wire:model="form.needed_by" />
                        <flux:error name="form.needed_by" />
                    </flux:field>
                </div>

                <flux:field>
                    <flux:label>{{ __('Justification') }}</flux:label>
                    <flux:description>{{ __('Why these people are needed') }}</flux:description>
                    <flux:textarea rows="3" wire:model="form.justification" />
                    <flux:error name="form.justification" />
                </flux:field>
            </fieldset>

            @if ($request?->isDraft())
                <flux:separator />
                <flux:button size="sm" variant="ghost" icon="trash" class="!text-red-600" x-on:click="$dispatch('confirm', {
                    title: @js(__('Delete this request?')),
                    message: @js(__('Only possible while it is a draft.')),
                    confirmText: @js(__('Delete')),
                    onConfirm: () => $wire.delete(),
                })">{{ __('Delete') }}</flux:button>
            @endif
        </form>

        @if ($editable)
            <x-slot:footer>
                <flux:button type="submit" form="manpower-request-form" variant="primary">{{ __('Save draft') }}</flux:button>
            </x-slot:footer>
        @endif
    </x-ui.modal>
</div>
