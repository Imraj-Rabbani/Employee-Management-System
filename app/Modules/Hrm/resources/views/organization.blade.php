{{--
    HRM → Organization. One view for every section; the route passes $section (routes/web.php).
    Each section = a DataTable + its modal form (App\Modules\Hrm\Livewire\Organization).
--}}
@php
    $sections = [
        'entity-types' => [__('Entity types'), 'entity-type-table', 'entity-type-form', 'create-entity-type', __('New entity type'), __('The kinds of entity your company uses, e.g. Department, Division, Branch, Location, Area.')],
        'entities' => [__('Entities'), 'entity-table', 'entity-form', 'create-entity', __('New entity'), __('The items of each type, e.g. Department → IT, Finance; Branch → Gulshan. Each can have a head for approvals.')],
        'positions' => [__('Positions'), 'position-table', 'position-form', 'create-position', __('New position'), __('Seats in the structure and who reports to whom.')],
        'designations' => [__('Designations'), 'designation-table', 'designation-form', 'create-designation', __('New designation'), __('Job titles as they appear on letters, e.g. Senior Officer.')],
        'grades' => [__('Grades'), 'grade-table', 'grade-form', 'create-grade', __('New grade'), __('Pay levels, in order. A salary profile (Payroll) can be linked to each grade.')],
        'employment-types' => [__('Employment types'), 'employment-type-table', 'employment-type-form', 'create-employment-type', __('New employment type'), __('Permanent, contract, part time ... Workflows can be limited to one type.')],
    ];
    [$label, $table, $form, $createEvent, $createLabel, $intro] = $sections[$section];
@endphp

<x-layouts.app :title="__('Organization')">
    <div class="space-y-4">
        <x-ui.section-tabs section="hrm.organization" />

        <div class="flex items-center justify-between gap-4">
            <flux:subheading>{{ $intro }}</flux:subheading>
            <flux:button variant="primary" icon="plus" x-on:click="$dispatch('{{ $createEvent }}')">{{ $createLabel }}</flux:button>
        </div>

        @livewire('hrm::organization.'.$table, key($table))
        @livewire('hrm::organization.'.$form, key($form))
    </div>
</x-layouts.app>
