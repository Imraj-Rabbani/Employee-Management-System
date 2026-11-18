{{--
    Payroll → Salary Setup. One view for every section; the route passes $section (routes/web.php).
    Each list section = a DataTable + its modal form (App\Modules\Payroll\Livewire); "settings" and "import" are one component each.
--}}
@php
    $sections = [
        'heads' => [__('Salary heads'), 'salary-head-table', 'salary-head-form', __('The pay components: Basic, House rent, PF, Tax ... Each head has its own calculation rule.')],
        'profiles' => [__('Salary profiles'), 'salary-profile-table', 'salary-profile-form', __('Salary structures, usually one per grade: what each head is worth next to the basic. Apply one to a basic or gross to see every head.')],
        'pay-scales' => [__('Pay scale profiles'), 'pay-scale-profile-table', 'pay-scale-profile-form', __('How payroll treats a group of employees: arrears, the joining threshold day, leave without pay.')],
    ];
@endphp

<x-layouts.app :title="__('Salary setup')">
    <div class="space-y-4">
        <x-ui.section-tabs section="payroll.setup" />

        @if ($section === 'import')
            @livewire('payroll::salary-imports')
        @elseif ($section === 'settings')
            <flux:subheading>{{ __('Company-wide payroll settings.') }}</flux:subheading>
            @livewire('payroll::payroll-settings-form')
        @else
            @php [$label, $table, $form, $intro] = $sections[$section]; @endphp
            <div class="flex items-center justify-between gap-4">
                <flux:subheading>{{ $intro }}</flux:subheading>
            </div>

            @livewire('payroll::'.$table, key($table))
            @livewire('payroll::'.$form, key($form))
        @endif
    </div>
</x-layouts.app>
