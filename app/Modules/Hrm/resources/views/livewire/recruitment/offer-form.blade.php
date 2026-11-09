@php
    $verdicts = ['below' => ['warning', __('Below')], 'inside' => ['success', __('Within')], 'above' => ['danger', __('Above')]];
@endphp
<div>
    <x-ui.modal name="offer-form" size="lg"
        :heading="$offerId ? __('Change offer') : __('Make offer')"
        :subheading="$application ? $application->applicant->name.' · '.$application->jobPosting->title : null">
        <form wire:submit="save" id="offer-form" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input wire:model.live.debounce.400ms="form.gross_salary" type="number" min="1" step="0.01" :label="__('Gross salary (monthly)')" required />
                <x-form.select wire:model.live="form.grade_id" :label="__('Grade')" :options="$grades" :placeholder="__('None')" />
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
                <x-form.select wire:model="form.designation_id" :label="__('Designation')" :options="$designations" required />
                <x-form.select wire:model="form.employment_type_id" :label="__('Employment type')" :options="$types" :placeholder="__('None')" />
                <x-form.date wire:model="form.joining_date" :label="__('Joining date')" required />
                <x-form.date wire:model="form.expires_on" :label="__('Answer by')" :hint="__('The offer is valid until this date')" />
            </div>
            <x-form.textarea wire:model="form.terms" :label="__('Terms')" rows="4" :hint="__('Extra conditions printed in the offer letter, e.g. probation period')" />
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="offer-form" variant="primary">{{ $offerId ? __('Save') : __('Save draft') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
