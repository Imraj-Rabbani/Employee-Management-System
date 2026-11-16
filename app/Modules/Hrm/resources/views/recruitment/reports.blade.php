{{--
    HRM → Recruitment Reports. One view, one URL per report; the route passes $report.
    Each report is a Livewire component in App\Modules\Hrm\Livewire\Recruitment\Reports.
--}}
@php
    $reports = [
        'open-positions' => [__('Open positions'), 'open-positions-report', __('Approved manpower requests that still have people to hire.')],
        'funnel' => [__('Pipeline funnel'), 'pipeline-funnel-report', __('Per job posting: how many applicants reached each step.')],
        'time-to-hire' => [__('Time to hire'), 'time-to-hire-report', __('How long hiring takes, from application and from approval.')],
    ];
    // Not "$component": Blade uses that name itself inside <x-...> components.
    [, $reportComponent, $intro] = $reports[$report];
@endphp

<x-layouts.app :title="__('Recruitment')">
    <div class="space-y-4">
        <x-ui.section-tabs section="hrm.recruitment" />

        {{-- Second row of tabs: each one is its own page. --}}
        <nav class="flex gap-1 overflow-x-auto border-b border-zinc-200 [scrollbar-width:none] dark:border-white/10" aria-label="{{ __('Recruitment reports') }}">
            @foreach ($reports as $key => $tab)
                <a href="{{ route('hrm.recruitment.reports.'.$key) }}" wire:navigate @class([
                    '-mb-px shrink-0 whitespace-nowrap border-b-2 px-2.5 py-1.5 text-xs font-medium transition',
                    'border-brand-800 text-brand-900 dark:border-white dark:text-white' => $key === $report,
                    'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $key !== $report,
                ]) @if ($key === $report) aria-current="page" @endif>{{ $tab[0] }}</a>
            @endforeach
        </nav>

        <flux:subheading>{{ $intro }}</flux:subheading>

        @livewire('hrm::recruitment.reports.'.$reportComponent, key($reportComponent))
    </div>
</x-layouts.app>
