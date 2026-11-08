<div>
    <x-ui.modal :name="$modal" size="lg" :heading="__('Edit personal details')">
        <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-[1fr_2fr_2fr]">
                <x-form.input wire:model="form.title" :label="__('Title')" :hint="__('Mr, Ms, Dr')" />
                <x-form.input wire:model="form.first_name" :label="__('First name')" required />
                <x-form.input wire:model="form.last_name" :label="__('Last name')" />
            </div>
            <x-form.input wire:model="form.name_local" :label="__('Name (local)')" :hint="__('e.g. the name in Bangla')" />

            <div class="grid gap-4 sm:grid-cols-3">
                <x-form.select wire:model="form.gender" :label="__('Gender')" :options="$genders" :placeholder="__('Not set')" />
                <x-form.date wire:model="form.birth_date" :label="__('Date of birth')" />
                <x-form.select wire:model="form.marital_status" :label="__('Marital status')" :options="$maritalStatuses" :placeholder="__('Not set')" />
                <x-form.select wire:model="form.blood_group" :label="__('Blood group')" :options="$bloodGroups" :placeholder="__('Not set')" />
                <x-form.select wire:model="form.religion" :label="__('Religion')" :options="$religions" :placeholder="__('Not set')" />
                <x-form.input wire:model="form.nationality" :label="__('Nationality')" />
            </div>

            <flux:separator :text="__('Identity')" />
            <div class="grid gap-4 sm:grid-cols-3">
                <x-form.input wire:model="form.national_id" :label="__('National ID')" />
                <x-form.input wire:model="form.tin" :label="__('TIN')" />
                <x-form.input wire:model="form.passport_no" :label="__('Passport no.')" />
            </div>

            <flux:separator :text="__('Contact')" />
            <div class="grid gap-4 sm:grid-cols-3">
                <x-form.input wire:model="form.mobile" :label="__('Mobile')" type="tel" />
                <x-form.input wire:model="form.personal_email" :label="__('Personal email')" type="email" />
                <x-form.input wire:model="form.office_email" :label="__('Office email')" type="email" />
            </div>
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="{{ $modal }}" variant="primary">{{ __('Save') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
