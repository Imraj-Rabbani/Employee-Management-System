<div>
    <x-ui.modal name="employee-create-form" size="lg" :heading="__('New employee')"
        :subheading="__('The basics only. You fill in the rest on the profile, which opens after saving.')">
        <form wire:submit="save" id="employee-create-form" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-[1fr_2fr_2fr]">
                <x-form.input wire:model="form.code" :label="__('Employee code')" required />
                <x-form.input wire:model="form.first_name" :label="__('First name')" required />
                <x-form.input wire:model="form.last_name" :label="__('Last name')" />
            </div>
            <div class="grid gap-4 sm:grid-cols-[1fr_2fr_2fr]">
                <x-form.input wire:model="form.title" :label="__('Title')" :hint="__('Mr, Ms, Dr')" />
                <x-form.input wire:model="form.name_local" :label="__('Name (local)')" />
                <x-form.select wire:model="form.gender" :label="__('Gender')" :options="$genders" :placeholder="__('Not set')" />
            </div>

            <flux:separator :text="__('Job')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.date wire:model="form.join_date" :label="__('Join date')" required />
                <x-form.select wire:model="form.org_unit_id" :label="__('Org unit')" :options="$units" :placeholder="__('Choose…')" required />
                <x-form.select wire:model="form.designation_id" :label="__('Designation')" :options="$designations" :placeholder="__('None')" />
                <x-form.select wire:model="form.employment_type_id" :label="__('Employment type')" :options="$types" :placeholder="__('None')" />
                <x-form.select wire:model="form.grade_id" :label="__('Grade')" :options="$grades" :placeholder="__('None')" />
            </div>
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="employee-create-form" variant="primary">{{ __('Add employee') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
