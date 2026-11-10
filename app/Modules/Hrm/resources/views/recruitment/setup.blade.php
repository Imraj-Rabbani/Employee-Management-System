{{--
    HRM → Recruitment → Setup. One view for both sections; the route passes $section (routes/web.php).
    Each section = a DataTable + its modal form (App\Modules\Hrm\Livewire\Recruitment). Same layout as Organization.
--}}
@php
    $sections = [
        'factors' => [__('Interview factors'), 'interview-factor-table', 'interview-factor-form', 'create-interview-factor', __('New factor'), __('What interviewers score, e.g. Communication. Weights of the active factors should add up to 100%.')],
        'reasons' => [__('Rejection reasons'), 'rejection-reason-table', 'rejection-reason-form', 'create-rejection-reason', __('New reason'), __('Picked when an application is rejected or withdrawn.')],
    ];
    [$label, $table, $form, $createEvent, $createLabel, $intro] = $sections[$section];
@endphp

<x-layouts.app :title="__('Recruitment')">
    <div class="space-y-4">
        <x-ui.section-tabs section="hrm.recruitment" />

        <x-ui.link-tabs size="sm" :label="__('Recruitment setup')" :tabs="collect($sections)->map(fn ($x, $key) => ['label' => $x[0], 'href' => route('hrm.recruitment.setup.'.$key), 'current' => $key === $section])->all()" />

        <div class="flex items-center justify-between gap-4">
            <flux:subheading>{{ $intro }}</flux:subheading>
            <flux:button variant="primary" icon="plus" x-on:click="$dispatch('{{ $createEvent }}')">{{ $createLabel }}</flux:button>
        </div>

        @livewire('hrm::recruitment.'.$table, key($table))
        @livewire('hrm::recruitment.'.$form, key($form))
    </div>
</x-layouts.app>
