<div>
    <div class="glass overflow-hidden rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ __('Entities') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Department, branch ... of :name, and when. One current entity per type. Newest first.', ['name' => $employee->name]) }}</flux:text>
            </div>
            @if ($canUpdate)
                <div class="flex shrink-0 items-center gap-2">
                    <flux:button size="sm" icon="arrows-right-left" wire:click="open">{{ __('Change entity') }}</flux:button>
                </div>
            @endif
        </div>

        @if ($rows->isEmpty())
            <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                    <flux:icon name="building-office" />
                </div>
                <flux:heading size="lg" class="mt-4">{{ __('No entity yet') }}</flux:heading>
                <flux:text class="mt-1 max-w-sm">{{ __('Use “Change entity” to place this employee.') }}</flux:text>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    <tr class="border-b border-white/50 dark:border-white/10">
                        <th class="px-6 py-3 font-medium">{{ __('Type') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Entity') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('From') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('To') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Reason') }}</th>
                        <th class="px-6 py-3"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-white/50 last:border-0 dark:border-white/10" wire:key="entity-row-{{ $row->id }}">
                            <td class="px-6 py-3 text-zinc-600 dark:text-zinc-300">{{ $row->type?->name }}</td>
                            <td class="px-6 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $row->entity?->name }}</td>
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
                                @if ($canUpdate && isset($undoable[$row->id]))
                                    <flux:button size="xs" variant="ghost" icon="arrow-uturn-left" x-on:click="$dispatch('confirm', {
                                        title: @js(__('Undo this change?')),
                                        message: @js(__('The row is deleted and the employee goes back to the previous :type.', ['type' => $row->type?->name])),
                                        confirmText: @js(__('Undo')),
                                        onConfirm: () => $wire.undo({{ $undoable[$row->id] }}),
                                    })">{{ __('Undo') }}</flux:button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if ($canUpdate)
        <x-ui.modal name="employee-entity-change" :heading="__('Change entity')"
            :subheading="__('The current row ends the day before the new one starts.')">
            <form wire:submit="save" id="employee-entity-change" class="space-y-5">
                <flux:field>
                    <flux:label>{{ __('New entity') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('Only the current entity of the same type ends; the others stay.') }}</flux:description>
                    <flux:select aria-required="true" wire:model="form.entity_id">
                        <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                        @foreach ($entities as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.entity_id" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('From') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('Today or earlier. Moves with a future date are not supported yet.') }}</flux:description>
                    <flux:input type="date" aria-required="true" wire:model="form.effective_from" />
                    <flux:error name="form.effective_from" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Reason') }}</flux:label>
                    <flux:input :placeholder="__('e.g. Transfer, Restructuring')" wire:model="form.reason" />
                    <flux:error name="form.reason" />
                </flux:field>
            </form>

            <x-slot:footer>
                <flux:button type="submit" form="employee-entity-change" variant="primary">{{ __('Save') }}</flux:button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
