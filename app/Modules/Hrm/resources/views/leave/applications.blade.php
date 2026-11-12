{{--
    HRM → Leave → Applications (8.4). Leave applications of the employees the user can see; apply for one.
    Approvers answer in their Approvals inbox. [LV-04]
--}}
<x-layouts.app :title="__('Leave')">
    <div class="space-y-4">
        <x-ui.section-tabs section="hrm.leave" />

        <div class="flex flex-wrap items-center justify-between gap-4">
            <flux:subheading>{{ __('Days are taken from the balance when the last approver approves; withdrawing gives them back.') }}</flux:subheading>
            <flux:button variant="primary" icon="plus" x-on:click="$dispatch('create-leave')">{{ __('Apply for leave') }}</flux:button>
        </div>

        <livewire:hrm::leave.leave-application-table />
        <livewire:hrm::leave.leave-application-form />
        <livewire:hrm::leave.leave-application-details />
    </div>
</x-layouts.app>
