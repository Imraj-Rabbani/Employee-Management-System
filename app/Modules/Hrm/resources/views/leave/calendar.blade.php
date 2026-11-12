{{-- HRM → Leave → Calendar (8.7): who is on leave in a month. [LV-04] --}}
<x-layouts.app :title="__('Leave')">
    <div class="space-y-4">
        <x-ui.section-tabs section="hrm.leave" />
        <livewire:hrm::leave.leave-calendar />
        <livewire:hrm::leave.leave-application-details />
    </div>
</x-layouts.app>
