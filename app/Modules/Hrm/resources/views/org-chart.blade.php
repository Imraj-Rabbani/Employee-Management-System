{{-- HRM → Organization → Org chart. --}}
<x-layouts.app :title="__('Organization')">
    <div class="space-y-4">
        <x-ui.section-tabs section="hrm.organization" />

        <livewire:hrm::org-chart />
    </div>
</x-layouts.app>
