@php
    $verdicts = ['below' => ['warning', __('Below')], 'inside' => ['success', __('Within')], 'above' => ['danger', __('Above')]];
@endphp
<div>
    <x-ui.modal name="offer-form" size="lg"
        :heading="$offerId ? __('Change offer') : __('Make offer')"
        :subheading="$application ? $application->applicant->name.' · '.$application->jobPosting->title : null">
        <form wire:submit="save" id="offer-form" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Gross salary (monthly)') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" wire:model.live.debounce.400ms="form.gross_salary" type="number" min="1" step="0.01" />
                    <flux:error name="form.gross_salary" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Grade') }}</flux:label>
                    <flux:select wire:model.live="form.grade_id">
                        <flux:select.option value="">{{ __('None') }}</flux:select.option>
                        @foreach ($grades as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.grade_id" />
                </flux:field>
            </div>

            {{-- Live comparison while typing (JobOffer::comparisons() on the unsaved values). --}}
            @if ($comparisons)
                <div class="grid gap-2 sm:grid-cols-3">
                    @foreach ($comparisons as $row)
                        <div class="rounded-xl px-3 py-2 text-sm ring-1 ring-zinc-500/15">
                            <div class="text-xs text-zinc-500">{{ $row['label'] }}</div>
                            <div class="tabular-nums">{{ $row['range'] ?? '—' }}</div>
                            @if ($row['verdict'])
                                <x-ui.badge class="mt-1" :color="$verdicts[$row['verdict']][0]">{{ $verdicts[$row['verdict']][1] }}</x-ui.badge>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Designation') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:select aria-required="true" wire:model="form.designation_id">
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
                    <flux:label>{{ __('Joining date') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input type="date" aria-required="true" wire:model="form.joining_date" />
                    <flux:error name="form.joining_date" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Answer by') }}</flux:label>
                    <flux:description>{{ __('The offer is valid until this date') }}</flux:description>
                    <flux:input type="date" wire:model="form.expires_on" />
                    <flux:error name="form.expires_on" />
                </flux:field>
            </div>
            <flux:field>
                <flux:label>{{ __('Terms') }}</flux:label>
                <flux:description>{{ __('Extra conditions printed in the offer letter, e.g. probation period') }}</flux:description>
                <flux:textarea wire:model="form.terms" rows="4" />
                <flux:error name="form.terms" />
            </flux:field>
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="offer-form" variant="primary">{{ $offerId ? __('Save') : __('Save draft') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
