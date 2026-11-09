{{--
    HRM → Recruitment Reports (6.9). One view, one URL per report; the route passes $report.
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

<x-layouts.app :title="__('Recruitment reports')">
    <div class="space-y-4">
        <nav class="flex flex-wrap gap-1 border-b border-zinc-200 dark:border-white/10" aria-label="{{ __('Recruitment reports') }}">
            @foreach ($reports as $key => $r)
                <a href="{{ route('hrm.recruitment.reports.'.$key) }}" wire:navigate @class([
                    '-mb-px border-b-2 px-3 py-2 text-sm font-medium transition',
                    'border-brand-800 text-brand-900 dark:border-white dark:text-white' => $key === $report,
                    'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $key !== $report,
                ]) @if ($key === $report) aria-current="page" @endif>{{ $r[0] }}</a>
            @endforeach
        </nav>

        <flux:subheading>{{ $intro }}</flux:subheading>

        @livewire('hrm::recruitment.reports.'.$reportComponent, key($reportComponent))
    </div>
</x-layouts.app>
