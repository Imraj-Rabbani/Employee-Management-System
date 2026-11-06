{{--
    HRM → Organization. One view for every section; the route passes $section (routes/web.php).
    Each section = a DataTable + its modal form (App\Modules\Hrm\Livewire\Organization).
--}}
@php
    $sections = [
        'units' => [__('Org units'), 'org-unit-table', 'org-unit-form', 'create-org-unit', __('New org unit'), __('Your company structure. Each unit sits under another, except the top ones.')],
        'positions' => [__('Positions'), 'position-table', 'position-form', 'create-position', __('New position'), __('Seats in the structure and who reports to whom.')],
        'designations' => [__('Designations'), 'designation-table', 'designation-form', 'create-designation', __('New designation'), __('Job titles as they appear on letters, e.g. Senior Officer.')],
        'grades' => [__('Grades'), 'grade-table', 'grade-form', 'create-grade', __('New grade'), __('Pay levels, in order. Salary profiles (Phase 9) are set per grade.')],
        'branches' => [__('Branches'), 'branch-table', 'branch-form', 'create-branch', __('New branch'), __('Branch offices.')],
        'cost-centers' => [__('Cost centers'), 'cost-center-table', 'cost-center-form', 'create-cost-center', __('New cost center'), __('Where salary cost is booked in accounting.')],
        'employment-types' => [__('Employment types'), 'employment-type-table', 'employment-type-form', 'create-employment-type', __('New employment type'), __('Permanent, contract, part time ... Workflows can be limited to one type.')],
        'unit-levels' => [__('Unit levels'), 'org-unit-type-table', 'org-unit-type-form', 'create-org-unit-type', __('New unit level'), __('The levels of your structure and which level sits under which, e.g. Division → Department.')],
    ];
    [$label, $table, $form, $createEvent, $createLabel, $intro] = $sections[$section];
@endphp

<x-layouts.app :title="__('Organization')">
    <div class="space-y-4">
        {{-- Section links: real pages (bookmarkable), styled like tabs. --}}
        <nav class="flex flex-wrap gap-1 border-b border-zinc-200 dark:border-white/10" aria-label="{{ __('Organization') }}">
            @foreach ($sections as $key => $s)
                <a href="{{ route('hrm.org.'.$key) }}" wire:navigate @class([
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

        {{-- Org units: Tree (default) or List, chosen with ?view=list. --}}
        @if ($section === 'units')
            @php $view = request()->query('view') === 'list' ? 'list' : 'tree'; @endphp
            <div class="flex gap-1">
                <flux:button size="sm" :variant="$view === 'tree' ? 'filled' : 'ghost'" icon="share" :href="route('hrm.org.units')" wire:navigate>{{ __('Tree') }}</flux:button>
                <flux:button size="sm" :variant="$view === 'list' ? 'filled' : 'ghost'" icon="list-bullet" :href="route('hrm.org.units', ['view' => 'list'])" wire:navigate>{{ __('List') }}</flux:button>
            </div>
            @if ($view === 'tree')
                @php $table = 'org-unit-tree'; @endphp
            @endif
        @endif

        @livewire('hrm::organization.'.$table, key($table))
        @livewire('hrm::organization.'.$form, key($form))
    </div>
</x-layouts.app>
