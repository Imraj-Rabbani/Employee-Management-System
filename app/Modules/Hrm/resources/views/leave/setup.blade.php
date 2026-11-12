{{--
    HRM → Leave setup (8.1). One view for every section; the route passes $section (routes/web.php).
    Each section = a DataTable + its modal form (App\Modules\Hrm\Livewire\Leave). Same layout as Payroll → Attendance.
--}}
@php
    $sections = [
        'types' => ['leave-type-table', 'leave-type-form', 'create-leave-type', __('New leave type'), __('Kinds of leave and their rules: who may take it, how many days at once, how it is earned and carried forward.')],
        'profiles' => ['leave-profile-table', 'leave-profile-form', 'create-leave-profile', __('New leave profile'), __('The days per year of each leave type. Each employee gets one profile (Job tab).')],
    ];
    [$table, $form, $createEvent, $createLabel, $intro] = $sections[$section];
@endphp

<x-layouts.app :title="__('Leave')">
    <div class="space-y-4">
        <x-ui.section-tabs section="hrm.leave" />

        <div class="flex items-center justify-between gap-4">
            <flux:subheading>{{ $intro }}</flux:subheading>
            <flux:button variant="primary" icon="plus" x-on:click="$dispatch('{{ $createEvent }}')">{{ $createLabel }}</flux:button>
        </div>

        @livewire('hrm::leave.'.$table, key($table))
        @livewire('hrm::leave.'.$form, key($form))
    </div>
</x-layouts.app>
