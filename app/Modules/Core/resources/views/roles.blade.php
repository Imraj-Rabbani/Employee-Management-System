<x-layouts.app :title="__('Roles & Permissions')">
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-4">
            <flux:subheading>{{ __('A role is a set of permissions. Each user has one role.') }}</flux:subheading>

            <flux:button variant="primary" icon="plus" x-on:click="$dispatch('create-role')">{{ __('New role') }}</flux:button>
        </div>

        <livewire:core::roles-table />
        <livewire:core::role-form />
    </div>
</x-layouts.app>
