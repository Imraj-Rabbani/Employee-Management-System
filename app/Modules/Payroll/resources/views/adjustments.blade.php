{{--
    Payroll → Adjustments. One-time earnings / deductions per employee and month; approved ones are paid by
    that month's regular payroll run.
--}}
<x-layouts.app :title="__('Payroll adjustments')">
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('Payroll adjustments') }}</flux:heading>
                <flux:subheading>{{ __('One-time earnings and deductions. Once approved, the regular run of that month pays them.') }}</flux:subheading>
            </div>
        </div>

        <livewire:payroll::adjustment-table />
        @can('payroll.adjustment.manage')
            <livewire:payroll::adjustment-form />
            <livewire:payroll::adjustment-import />
        @endcan
    </div>
</x-layouts.app>
