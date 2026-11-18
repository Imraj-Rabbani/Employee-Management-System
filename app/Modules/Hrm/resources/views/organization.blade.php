{{--
    HRM → Organization. One view for every section; the route passes $section (routes/web.php).
    Each section = a DataTable + its modal form (App\Modules\Hrm\Livewire\Organization).
--}}
@php
    $sections = [
        'entity-types' => [__('Entity types'), 'entity-type-table', 'entity-type-form', __('The kinds of entity your company uses, e.g. Department, Division, Branch, Location, Area.')],
        'entities' => [__('Entities'), 'entity-table', 'entity-form', __('The items of each type, e.g. Department → IT, Finance; Branch → Gulshan. Each can have a head for approvals.')],
        'positions' => [__('Positions'), 'position-table', 'position-form', __('Seats in the structure and who reports to whom.')],
        'designations' => [__('Designations'), 'designation-table', 'designation-form', __('Job titles as they appear on letters, e.g. Senior Officer.')],
        'grades' => [__('Grades'), 'grade-table', 'grade-form', __('Pay levels, in order. A salary profile (Payroll) can be linked to each grade.')],
        'employment-types' => [__('Employment types'), 'employment-type-table', 'employment-type-form', __('Permanent, contract, part time ... Workflows can be limited to one type.')],
    ];
    [$label, $table, $form, $intro] = $sections[$section];
@endphp

<x-layouts.app :title="__('Organization')">
    <div class="space-y-4">
        <x-ui.section-tabs section="hrm.organization" />

        <div class="flex items-center justify-between gap-4">
            <flux:subheading>{{ $intro }}</flux:subheading>
        </div>

        @livewire('hrm::organization.'.$table, key($table))
        @livewire('hrm::organization.'.$form, key($form))
    </div>
</x-layouts.app>
