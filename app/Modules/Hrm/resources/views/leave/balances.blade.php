{{-- HRM → Leave → Balances (8.7): the balance report of a leave year. [LV-03] --}}
<x-layouts.app :title="__('Leave')">
    <div class="space-y-4">
        <x-ui.section-tabs section="hrm.leave" />
        <flux:subheading>{{ __('Every number comes from the leave ledger. Open an employee\'s Leave tab to see the entries behind it.') }}</flux:subheading>
        <livewire:hrm::leave.leave-balance-table />
    </div>
</x-layouts.app>
