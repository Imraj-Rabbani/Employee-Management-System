{{--
    HRM → Recruitment → Config. One view for all sections; the route passes $section (routes/web.php).
    Each section = a DataTable + its modal form (App\Modules\Hrm\Livewire\Recruitment). Same layout as Organization.
--}}
@php
    $sections = [
        'questions' => [__('Interview questions'), 'interview-question-table', 'interview-question-form', __('All questions interviewers can score (1–5). Group them into a question set to use them in a stage.')],
        'sets' => [__('Question sets'), 'question-set-table', 'question-set-form', __('A named group of questions with a weight each (adding up to 100%). Each interview stage uses one set.')],
        'reasons' => [__('Rejection reasons'), 'rejection-reason-table', 'rejection-reason-form', __('Picked when an application is rejected or withdrawn.')],
    ];
    [$label, $table, $form, $intro] = $sections[$section];
@endphp

<x-layouts.app :title="__('Recruitment')">
    <div class="space-y-4">
        <x-ui.section-tabs section="hrm.recruitment" />

        {{-- Second row of tabs: each one is its own page. --}}
        <nav class="flex gap-1 overflow-x-auto border-b border-zinc-200 [scrollbar-width:none] dark:border-white/10" aria-label="{{ __('Recruitment config') }}">
            @foreach ($sections as $key => $tab)
                <a href="{{ route('hrm.recruitment.config.'.$key) }}" wire:navigate @class([
                    '-mb-px shrink-0 whitespace-nowrap border-b-2 px-2.5 py-1.5 text-xs font-medium transition',
                    'border-brand-800 text-brand-900 dark:border-white dark:text-white' => $key === $section,
                    'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $key !== $section,
                ]) @if ($key === $section) aria-current="page" @endif>{{ $tab[0] }}</a>
            @endforeach
        </nav>

        <div class="flex items-center justify-between gap-4">
            <flux:subheading>{{ $intro }}</flux:subheading>
        </div>

        @livewire('hrm::recruitment.'.$table, key($table))
        @livewire('hrm::recruitment.'.$form, key($form))
    </div>
</x-layouts.app>
