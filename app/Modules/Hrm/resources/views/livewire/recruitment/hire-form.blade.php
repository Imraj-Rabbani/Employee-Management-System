<div>
    <x-ui.modal name="hire-form" size="lg" :heading="$offer ? __('Hire :name', ['name' => $offer->application->applicant->name]) : __('Hire')"
        :subheading="__('Makes the employee from the offer: personal details, education, work history and CV are copied. You complete the rest on the profile.')">
        @if ($offer)
            <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3 mb-6">
                @foreach ([
                        __('Designation') => $offer->designation?->name,
                        __('Grade') => $offer->grade?->name,
                        __('Employment type') => $offer->employmentType?->name,
                        __('Agreed gross salary') => number_format((float) $offer->gross_salary),
                ] as $detailLabel => $detailValue)
                    <div>
                        <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $detailLabel }}</dt>
                        <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                            @if ($detailValue === null || $detailValue === '')
                                <span class="text-zinc-400">—</span>
                            @else
                                {{ $detailValue }}
                            @endif
                        </dd>
                    </div>
                @endforeach
            </dl>
        @endif

        <form wire:submit="save" id="hire-form" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Employee code') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" wire:model="form.code" />
                    <flux:error name="form.code" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Join date') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input type="date" aria-required="true" wire:model="form.join_date" />
                    <flux:error name="form.join_date" />
                </flux:field>

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
                    <flux:label>{{ __('Position') }}</flux:label>
                    <flux:description>{{ __('Free seats of the entity') }}</flux:description>
                    <flux:select wire:model="form.position_id">
                        <flux:select.option value="">{{ __('None') }}</flux:select.option>
                        @foreach ($positions as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.position_id" />
                </flux:field>
            </div>
            <flux:field>
                <flux:label>{{ __('Office email') }}</flux:label>
                <flux:input wire:model="form.office_email" type="email" />
                <flux:error name="form.office_email" />
            </flux:field>
            <flux:text class="text-xs">{{ __('Set the salary afterwards, on the employee\'s Salary tab.') }}</flux:text>
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="hire-form" variant="primary" icon="user-plus">{{ __('Hire') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
