<div>
    <x-ui.modal :name="$modal" size="lg" :heading="__('Edit job details')">
        <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input wire:model="form.code" :label="__('Employee code')" required />
                <x-form.input wire:model="form.access_code" :label="__('Attendance ID')" :hint="__('The ID on the attendance device.')" />
            </div>

            <flux:separator :text="__('Placement')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.select wire:model="form.org_unit_id" :label="__('Org unit')" :options="$units" :placeholder="__('Choose…')" required />
                <x-form.select wire:model="form.designation_id" :label="__('Designation')" :options="$designations" :placeholder="__('None')" />
                <x-form.select wire:model="form.grade_id" :label="__('Grade')" :options="$grades" :placeholder="__('None')" />
                <x-form.select wire:model="form.position_id" :label="__('Position')" :options="$positions" :placeholder="__('None')" />
                <x-form.select wire:model="form.branch_id" :label="__('Branch')" :options="$branches" :placeholder="__('None')" />
                <x-form.select wire:model="form.cost_center_id" :label="__('Cost center')" :options="$costCenters" :placeholder="__('Same as the unit')" />
                <x-form.select wire:model="form.employment_type_id" :label="__('Employment type')" :options="$types" :placeholder="__('None')" />
            </div>

            <flux:separator :text="__('Reports to')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.select wire:model="form.supervisor_id" :label="__('Supervisor')" :options="$people" :placeholder="__('Nobody')" />
                <x-form.select wire:model="form.dotted_line_manager_id" :label="__('Dotted-line manager')" :options="$people" :placeholder="__('Nobody')" />
            </div>

            <flux:separator :text="__('Dates & pay')" />
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.date wire:model="form.join_date" :label="__('Join date')" required />
                <x-form.date wire:model="form.planned_confirmation_date" :label="__('Planned confirmation')" />
                <x-form.date wire:model="form.confirmation_date" :label="__('Confirmed on')" />
                <x-form.select wire:model="form.payment_method" :label="__('Payment method')" :options="$paymentMethods" :placeholder="__('Not set')" />
            </div>
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="{{ $modal }}" variant="primary">{{ __('Save') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
