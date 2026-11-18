{{-- Super-admin home (/admin): the list of companies. --}}
<x-layouts.app :title="__('Platform admin')">
    <div class="space-y-4">
        <div class="flex items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('Companies') }}</flux:heading>
                <flux:subheading>{{ __('Every company on the platform, each with its own database.') }}</flux:subheading>
            </div>

        </div>

        <livewire:admin.tenants-table />
        <livewire:admin.create-tenant-form />
        <livewire:admin.edit-tenant-form />
    </div>
</x-layouts.app>
