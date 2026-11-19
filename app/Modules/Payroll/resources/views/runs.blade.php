{{--
    Payroll → Payroll runs. The list + the New / Edit run modal.
--}}
<x-layouts.app :title="__('Payroll runs')">
    <div class="space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-4">
            <div>
                <flux:heading size="xl">{{ __('Payroll runs') }}</flux:heading>
                <flux:subheading>{{ __('One run pays one period. Make it, calculate it, review it, then submit it for approval.') }}</flux:subheading>
            </div>
        </div>

        <livewire:payroll::payroll-run-table />
        @can('payroll.run.process')
            <livewire:payroll::payroll-run-form />
        @endcan
    </div>
</x-layouts.app>
