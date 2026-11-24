<x-layouts.app :title="__('Global Config')">
    <div class="space-y-4">
        <x-ui.section-tabs section="core.config" />

        <livewire:core::users-table />

        @can('core.users.manage')
            <livewire:core::user-role-form />
        @endcan
    </div>
</x-layouts.app>
