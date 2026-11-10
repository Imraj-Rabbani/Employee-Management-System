<div>
    <x-ui.modal name="hire-form" size="lg" :heading="$offer ? __('Hire :name', ['name' => $offer->application->applicant->name]) : __('Hire')"
        :subheading="__('Makes the employee from the offer: personal details, education, work history and CV are copied. You complete the rest on the profile.')">
        @if ($offer)
            <x-ui.details class="mb-6" :items="[
                __('Designation') => $offer->designation?->name,
                __('Grade') => $offer->grade?->name,
                __('Employment type') => $offer->employmentType?->name,
                __('Agreed gross salary') => number_format((float) $offer->gross_salary),
            ]" />
        @endif

        <form wire:submit="save" id="hire-form" class="space-y-4">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input wire:model="form.code" :label="__('Employee code')" required />
                <x-form.date wire:model="form.join_date" :label="__('Join date')" required />
                <x-form.select wire:model.live="form.org_unit_id" :label="__('Org unit')" :options="$units" :placeholder="__('Choose…')" required />
                <x-form.select wire:model="form.position_id" :label="__('Position')" :options="$positions" :placeholder="__('None')" :hint="__('Free seats of the org unit')" />
            </div>
            <x-form.input wire:model="form.office_email" type="email" :label="__('Office email')" />
            <flux:text class="text-xs">{{ __('The salary is set later, on the employee (Payroll, Phase 9).') }}</flux:text>
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="hire-form" variant="primary" icon="user-plus">{{ __('Hire') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
