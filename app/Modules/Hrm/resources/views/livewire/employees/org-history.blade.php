<div>
    <x-ui.card :heading="__('Org unit history')" :subheading="__('Where :name worked, and when. Newest first.', ['name' => $employee->name])" :padding="false">
        @if ($canUpdate)
            <x-slot:actions>
                <flux:button size="sm" icon="arrows-right-left" wire:click="open">{{ __('Change org unit') }}</flux:button>
            </x-slot:actions>
        @endif

        @if ($rows->isEmpty())
            <x-ui.empty icon="building-office" :heading="__('No org unit yet')" :description="__('Use “Change org unit” to place this employee.')" />
        @else
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    <tr class="border-b border-white/50 dark:border-white/10">
                        <th class="px-6 py-3 font-medium">{{ __('Org unit') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('From') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('To') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Reason') }}</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-white/50 last:border-0 dark:border-white/10" wire:key="org-row-{{ $row->id }}">
                            <td class="px-6 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $row->orgUnit?->path() }}</td>
                            <td class="px-6 py-3">{{ format_date($row->effective_from) }}</td>
                            <td class="px-6 py-3">
                                @if ($row->isOpen())
                                    <x-ui.badge color="success">{{ __('Current') }}</x-ui.badge>
                                @else
                                    {{ format_date($row->effective_to) }}
                                @endif
                            </td>
                            <td class="px-6 py-3 text-zinc-600 dark:text-zinc-300">{{ $row->reason ?? '—' }}</td>
                            <td class="px-6 py-3 text-right">
                                @if ($canUpdate && $loop->first && $rows->count() > 1)
                                    <flux:button size="xs" variant="ghost" icon="arrow-uturn-left" x-on:click="$dispatch('confirm', {
                                        title: @js(__('Undo this change?')),
                                        message: @js(__('The row is deleted and the employee goes back to the previous org unit.')),
                                        confirmText: @js(__('Undo')),
                                        onConfirm: () => $wire.undo(),
                                    })">{{ __('Undo') }}</flux:button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    @if ($canUpdate)
        <x-ui.modal name="employee-org-change" :heading="__('Change org unit')"
            :subheading="__('The current row ends the day before the new one starts.')">
            <form wire:submit="save" id="employee-org-change" class="space-y-5">
                <x-form.select wire:model="form.org_unit_id" :label="__('New org unit')" :options="$units" :placeholder="__('Choose…')" required />
                <x-form.date wire:model="form.effective_from" :label="__('From')" required :hint="__('Today or earlier. Future moves come with transfers (Phase 13).')" />
                <x-form.input wire:model="form.reason" :label="__('Reason')" :hint="__('e.g. Transfer, Restructuring')" />
            </form>

            <x-slot:footer>
                <flux:button type="submit" form="employee-org-change" variant="primary">{{ __('Save') }}</flux:button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
