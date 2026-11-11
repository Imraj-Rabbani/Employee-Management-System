{{--
    Payroll → Attendance → one roster (7.2). The route passes $roster (already checked with visibleTo).
    Header (Edit opens RosterForm), the grid with its actions (RosterGrid), and the approval timeline.
--}}
@php [$statusLabel, $statusColor] = $roster->statusBadge(); @endphp

<x-layouts.app :title="$roster->number()">
    <div class="space-y-4">
        <x-ui.section-tabs section="payroll.attendance" />

        <flux:link :href="route('payroll.attendance.rosters')" wire:navigate class="inline-flex items-center gap-1 text-sm">
            <flux:icon.arrow-left variant="micro" /> {{ __('All rosters') }}
        </flux:link>

        <x-ui.card :heading="$roster->number().' · '.$roster->title">
            <x-slot:actions>
                <x-ui.badge :color="$statusColor">{{ $statusLabel }}</x-ui.badge>
                @if ($roster->isEditable())
                    <flux:button size="sm" icon="pencil-square" x-on:click="$dispatch('edit-roster', { id: {{ $roster->id }} })">{{ __('Edit') }}</flux:button>
                @endif
            </x-slot:actions>

            <x-ui.details :items="[
                __('Org unit') => $roster->orgUnit?->path(),
                __('Change of') => $roster->replaces ? new \Illuminate\Support\HtmlString(sprintf(
                    '<a href="%s" wire:navigate class="font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">%s</a>',
                    e(route('payroll.attendance.rosters.show', $roster->replaces->id)), e($roster->replaces->number().' · '.$roster->replaces->title),
                )) : null,
                __('Period') => format_date($roster->from_date).' – '.format_date($roster->to_date).' ('.trans_choice(':count day|:count days', count($roster->dates())).')',
                __('Note') => $roster->note,
            ]" />
        </x-ui.card>

        <livewire:payroll::attendance.roster-grid :roster-id="$roster->id" />

        @if ($roster->approvalStatus())
            <x-ui.card :heading="__('Approval')">
                <x-approval-timeline :document="$roster" />
            </x-ui.card>
        @endif

        <livewire:payroll::attendance.roster-form />
    </div>
</x-layouts.app>
