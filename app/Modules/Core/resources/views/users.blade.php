<x-layouts.app :title="__('Users')">
    <livewire:core::users-table />

    @can('core.users.manage')
        <livewire:core::user-role-form />
    @endcan
</x-layouts.app>
