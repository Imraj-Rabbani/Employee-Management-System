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

<x-layouts.app :title="__('Recruitment')">
    <div class="space-y-4">
        <x-ui.section-tabs section="hrm.recruitment" />

        <x-ui.link-tabs size="sm" :label="__('Recruitment reports')" :tabs="collect($reports)->map(fn ($x, $key) => ['label' => $x[0], 'href' => route('hrm.recruitment.reports.'.$key), 'current' => $key === $report])->all()" />

        <flux:subheading>{{ $intro }}</flux:subheading>

        @livewire('hrm::recruitment.reports.'.$reportComponent, key($reportComponent))
    </div>
</x-layouts.app>
