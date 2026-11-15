{{--
    Payroll → Salary Setup. One view for every section; the route passes $section (routes/web.php).
    Each section = a DataTable + its modal form (App\Modules\Payroll\Livewire). More sections come in 9.3 and 9.5.
--}}
@php
    $sections = [
        'heads' => [__('Salary heads'), 'salary-head-table', 'salary-head-form', 'create-salary-head', __('New salary head'), __('The pay components: Basic, House rent, PF, Tax ... Each head has its own calculation rule.')],
    ];
    [$label, $table, $form, $createEvent, $createLabel, $intro] = $sections[$section];
@endphp

<x-layouts.app :title="__('Salary setup')">
    <div class="space-y-4">
        <x-ui.section-tabs section="payroll.setup" />

        <div class="flex items-center justify-between gap-4">
            <flux:subheading>{{ $intro }}</flux:subheading>
            <flux:button variant="primary" icon="plus" x-on:click="$dispatch('{{ $createEvent }}')">{{ $createLabel }}</flux:button>
        </div>

        @livewire('payroll::'.$table, key($table))
        @livewire('payroll::'.$form, key($form))
    </div>
</x-layouts.app>
