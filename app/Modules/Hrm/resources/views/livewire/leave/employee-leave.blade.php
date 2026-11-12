{{-- Profile → Leave (EmployeeLeave): balances of one leave year; click a row for the ledger rows that make it up. --}}
@php
    // 1.5 → "1.5", 10.00 → "10"; $num shows "—" for nothing.
    $fmt = fn (float $v) => rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.');
    $num = fn (?float $v) => $v === null || $v == 0 ? '—' : $fmt($v);
    $columns = [
        'opening' => __('Opening'), 'earned' => __('Earned'), 'carried_forward' => __('Carried'), 'adjusted' => __('Corrections'),
        'taken' => __('Taken'), 'encashed' => __('Encashed'), 'expired' => __('Expired'),
    ];
@endphp

<div class="space-y-4">
    <x-ui.card :heading="__('Leave balances')" :subheading="$employee->leaveProfile
            ? __('Leave profile: :profile', ['profile' => $employee->leaveProfile->name])
            : __('No leave profile yet: set one on the Job tab.')" :padding="false">
        <x-slot:actions>
            <div class="flex items-center gap-2">
                <flux:select wire:model.live="year" size="sm" class="w-48" :aria-label="__('Leave year')">
                    @foreach ($years as $value => $label)
                        <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                @if ($canEnter)
                    <flux:button size="sm" icon="plus" wire:click="open">{{ __('Add entry') }}</flux:button>
                @endif
            </div>
        </x-slot:actions>

        @if ($types->isEmpty())
            <x-ui.empty icon="calendar-days" :heading="__('No leave in :year', ['year' => $leaveYear->label()])"
                :description="__('Leave types come from the leave profile (Job tab).')" />
        @else
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        <tr class="border-b border-white/50 dark:border-white/10">
                            <th class="px-6 py-3 font-medium">{{ __('Leave type') }}</th>
                            <th class="px-3 py-3 text-right font-medium">{{ __('Per year') }}</th>
                            @foreach ($columns as $label)
                                <th class="px-3 py-3 text-right font-medium">{{ $label }}</th>
                            @endforeach
                            <th class="px-6 py-3 text-right font-medium">{{ __('Balance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($types as $type)
                            @php $balance = $balances->get($type->id); @endphp
                            <tr wire:key="leave-balance-{{ $type->id }}" wire:click="show({{ $type->id }})" @class([
                                'cursor-pointer border-b border-white/50 last:border-0 hover:bg-white/40 dark:border-white/10 dark:hover:bg-white/5',
                                'bg-white/40 dark:bg-white/5' => $typeId === $type->id,
                            ])>
                                <td class="px-6 py-3 font-medium text-zinc-900 dark:text-zinc-100">{{ $type->optionLabel() }}</td>
                                <td class="px-3 py-3 text-right">{{ $perYear->has($type->id) ? $num((float) $perYear[$type->id]) : __('Not in profile') }}</td>
                                @foreach (array_keys($columns) as $column)
                                    <td class="px-3 py-3 text-right tabular-nums">{{ $num($balance?->{$column}) }}</td>
                                @endforeach
                                {{-- Leave that needs no balance (e.g. without pay) only counts what was taken. --}}
                                <td @class(['px-6 py-3 text-right font-semibold tabular-nums', '!text-red-600' => $type->requires_balance && ($balance->balance ?? 0) < 0])>
                                    {{ $type->requires_balance ? $fmt($balance->balance ?? 0) : '—' }}
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </x-ui.card>

    @if ($typeId !== null)
        <x-ui.card :heading="__('How the balance was made')" :subheading="__('Every change, oldest first. Rows are never edited: mistakes are fixed with a correction.')" :padding="false">
            @if ($canEnter)
                <x-slot:actions>
                    <flux:button size="sm" variant="ghost" icon="plus" wire:click="open({{ $typeId }})">{{ __('Add entry') }}</flux:button>
                </x-slot:actions>
            @endif

            @if ($entries->isEmpty())
                <x-ui.empty icon="list-bullet" :heading="__('No entries in :year', ['year' => $leaveYear->label()])" />
            @else
                <table class="w-full text-sm">
                    <thead class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                        <tr class="border-b border-white/50 dark:border-white/10">
                            <th class="px-6 py-3 font-medium">{{ __('Date') }}</th>
                            <th class="px-3 py-3 font-medium">{{ __('What') }}</th>
                            <th class="px-3 py-3 text-right font-medium">{{ __('Days') }}</th>
                            <th class="px-6 py-3 font-medium">{{ __('Note') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($entries as $entry)
                            <tr wire:key="leave-entry-{{ $entry->id }}" class="border-b border-white/50 last:border-0 dark:border-white/10">
                                <td class="px-6 py-3">{{ format_date($entry->entry_date) }}</td>
                                <td class="px-3 py-3">{{ $entry->entry_type->label() }}</td>
                                <td @class(['px-3 py-3 text-right tabular-nums', 'text-green-700 dark:text-green-300' => $entry->days > 0, 'text-red-600' => $entry->days < 0])>
                                    {{ $entry->days > 0 ? '+' : '' }}{{ $fmt($entry->days) }}
                                </td>
                                <td class="px-6 py-3 text-zinc-600 dark:text-zinc-300">
                                    {{ $entry->note ?? '—' }}
                                    @if ($entry->expires_on)
                                        <span class="text-xs text-zinc-500">· {{ __('expires :date', ['date' => format_date($entry->expires_on)]) }}</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </x-ui.card>
    @endif

    @if ($canEnter)
        <x-ui.modal name="leave-entry" :heading="__('Add leave entry')" :subheading="__('Leave year :year', ['year' => $leaveYear->label()])">
            <form wire:submit="save" id="leave-entry" class="space-y-5">
                <x-form.select wire:model="form.leave_type_id" :label="__('Leave type')" :options="$typeOptions" :placeholder="__('Choose…')" required />
                <x-form.select wire:model.live="form.kind" :label="__('What')" required :options="[
                    'opening' => __('Opening balance'),
                    'adjustment' => __('Correction'),
                ]" :hint="$form['kind'] === 'opening'
                    ? __('The days the employee starts the year with, e.g. from the old system. Once per year.')
                    : __('Adds days, or takes them away with a minus: -1.5')" />
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.input type="number" step="0.01" wire:model="form.days" :label="__('Days')" required />
                    <x-form.date wire:model="form.date" :label="__('Date')" required />
                </div>
                <x-form.input wire:model="form.note" :label="__('Note')" :required="$form['kind'] !== 'opening'" :hint="__('Why: shown in the balance history.')" />
            </form>

            <x-slot:footer>
                <flux:button type="submit" form="leave-entry" variant="primary">{{ __('Save') }}</flux:button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
