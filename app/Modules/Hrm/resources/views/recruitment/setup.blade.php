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

<x-layouts.app :title="__('Recruitment setup')">
    <div class="space-y-4">
        <nav class="flex flex-wrap gap-1 border-b border-zinc-200 dark:border-white/10" aria-label="{{ __('Recruitment setup') }}">
            @foreach ($sections as $key => $s)
                <a href="{{ route('hrm.recruitment.setup.'.$key) }}" wire:navigate @class([
                    '-mb-px border-b-2 px-3 py-2 text-sm font-medium transition',
                    'border-brand-800 text-brand-900 dark:border-white dark:text-white' => $key === $section,
                    'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $key !== $section,
                ]) @if ($key === $section) aria-current="page" @endif>{{ $s[0] }}</a>
            @endforeach
        </nav>

        <div class="flex items-center justify-between gap-4">
            <flux:subheading>{{ $intro }}</flux:subheading>
            <flux:button variant="primary" icon="plus" x-on:click="$dispatch('{{ $createEvent }}')">{{ $createLabel }}</flux:button>
        </div>

        @livewire('hrm::recruitment.'.$table, key($table))
        @livewire('hrm::recruitment.'.$form, key($form))
    </div>
</x-layouts.app>
