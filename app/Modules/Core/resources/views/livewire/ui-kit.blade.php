<div class="space-y-6">
    {{-- x-ui.stat --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-ui.stat :label="__('Employees')" value="240" icon="users" :trend="__('+4 this month')" :trend-up="true" />
        <x-ui.stat :label="__('On leave today')" value="12" icon="calendar-days" />
        <x-ui.stat :label="__('Pending approvals')" value="7" icon="clock" :trend="__('+3 since yesterday')" :trend-up="false" />
        <x-ui.stat :label="__('Payroll (Oct)')" value="৳ 1.2 Cr" icon="banknotes" :trend="__('Locked')" />
    </div>

    {{-- x-ui.badge --}}
    <x-ui.card :heading="__('Badges')">
        <div class="flex flex-wrap gap-2">
            <x-ui.badge>{{ __('Draft') }}</x-ui.badge>
            <x-ui.badge color="brand">{{ __('Permanent') }}</x-ui.badge>
            <x-ui.badge color="success" dot>{{ __('Approved') }}</x-ui.badge>
            <x-ui.badge color="warning" dot>{{ __('Pending') }}</x-ui.badge>
            <x-ui.badge color="danger" dot>{{ __('Rejected') }}</x-ui.badge>
            <x-ui.badge color="info">{{ __('Probation') }}</x-ui.badge>
        </div>
    </x-ui.card>

    {{-- x-ui.empty + x-ui.modal trigger --}}
    <x-ui.card :heading="__('Employees')" :subheading="__('Empty state and form modal')">
        <x-slot:actions>
            <flux:modal.trigger name="employee-form">
                <flux:button variant="primary" size="sm" icon="plus">{{ __('Add employee') }}</flux:button>
            </flux:modal.trigger>
        </x-slot:actions>

        <x-ui.empty icon="users" :heading="__('No employees yet')" :description="__('Add your first employee to get started.')">
            <flux:modal.trigger name="employee-form">
                <flux:button variant="primary" icon="plus">{{ __('Add employee') }}</flux:button>
            </flux:modal.trigger>
        </x-ui.empty>
    </x-ui.card>

    {{-- x-ui.modal with every x-form.* field --}}
    <x-ui.modal name="employee-form" :heading="__('Add employee')" :subheading="__('Demo form: try saving it empty.')">
        <x-form.input wire:model="name" :label="__('Full name')" required />
        <x-form.input wire:model="email" type="email" :label="__('Email')" :hint="__('Used for login')" required />
        <x-form.select wire:model="department" :label="__('Department')" :options="$this->departments()" :placeholder="__('Choose…')" required />
        <x-form.date wire:model="joined_on" :label="__('Joining date')" max="{{ now()->toDateString() }}" required />
        <x-form.textarea wire:model="notes" :label="__('Notes')" />
        <x-form.file wire:model="photo" :label="__('Photo')" accept="image/*" :hint="__('JPG or PNG, max 2 MB')" />
        <x-form.toggle wire:model="active" :label="__('Active')" :hint="__('Inactive employees are hidden from lists')" />

        <x-slot:footer>
            <flux:button wire:click="save" variant="primary">{{ __('Save') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
