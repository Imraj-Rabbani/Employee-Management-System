{{--
    The roster grid (RosterGrid). Alpine holds the cells while you paint; Save / Fill send them to the server.
    The table is wire:ignore and the x-data wrapper wire:ignore.self: Livewire never redraws them, Alpine does
    (so painting is never undone by a redraw, and the cells in x-data are read only once).

    Painting: choose a brush, then click or drag over cells. Click a name = whole row, click a date = whole column.
--}}
@php
    $firstBrush = (string) ($shifts->firstWhere('is_default', true)?->id ?? $shifts->first()?->id ?? 'off');
@endphp

<div>
<div class="space-y-4" wire:ignore.self
    x-data="{
        cells: @js((object) array_map(fn ($row) => (object) $row, $cells)),
        codes: @js((object) $shiftCodes),
        editable: @js($editable),
        brush: @js($firstBrush),
        painting: false,
        dirty: false,
        busy: false,
        label(v) { return v === 'off' ? @js(__('Off')) : (v === 'call' ? @js(__('OC')) : (this.codes[v] ? this.codes[v][0] : '')) },
        title(v) { return v === 'off' ? @js(__('Day off')) : (v === 'call' ? @js(__('On call')) : (this.codes[v] ? this.codes[v][1] : @js(__('Not planned')))) },
        cls(v) {
            if (v === 'off') return 'bg-zinc-200/80 text-zinc-600 dark:bg-white/10 dark:text-zinc-300';
            if (v === 'call') return 'bg-amber-100 text-amber-800 dark:bg-amber-500/20 dark:text-amber-200';
            if (v) return 'bg-brand-800/10 text-brand-900 dark:bg-brand-400/20 dark:text-brand-100';
            return 'text-zinc-400';
        },
        paint(e, d) {
            if (! this.editable || this.cells[e][d] === this.brush) return;
            this.cells[e][d] = this.brush;
            this.dirty = true;
        },
        fillRow(e) { for (const d in this.cells[e]) this.paint(e, d) },
        fillCol(d) { for (const e in this.cells) this.paint(e, d) },
        async save() {
            this.busy = true;
            const saved = await $wire.save(this.cells);
            this.busy = false;
            if (saved) { this.cells = saved; this.dirty = false }
        },
        async fill() {
            this.busy = true;
            const filled = await $wire.fillEmpty(this.cells);
            this.busy = false;
            if (JSON.stringify(filled) !== JSON.stringify(this.cells)) { this.cells = filled; this.dirty = true }
        },
        needsSave(then) {
            if (this.dirty) {
                $dispatch('toast', { message: @js(__('Save your changes first.')), variant: 'danger' });
                return;
            }
            then();
        },
    }"
    x-on:mouseup.window="painting = false"
    x-on:roster-cells.window="cells = $event.detail.cells; dirty = false"
    x-on:beforeunload.window="if (dirty) { $event.preventDefault(); $event.returnValue = '' }"
>
    {{-- Toolbar --}}
    <x-ui.card>
        <div class="flex flex-wrap items-end justify-between gap-4">
            @if ($editable)
                <div class="flex flex-wrap items-end gap-3">
                    <flux:field>
                        <flux:label>{{ __('Brush') }}</flux:label>
                        <flux:select x-model="brush" size="sm" class="min-w-56">
                            @foreach ($shifts as $shift)
                                <flux:select.option :value="(string) $shift->id">{{ $shift->code }} · {{ $shift->optionLabel() }}</flux:select.option>
                            @endforeach
                            <flux:select.option value="off">{{ __('Off · day off') }}</flux:select.option>
                            <flux:select.option value="call">{{ __('OC · on call') }}</flux:select.option>
                            <flux:select.option value="">{{ __('Clear · not planned') }}</flux:select.option>
                        </flux:select>
                    </flux:field>
                    <flux:button size="sm" icon="sparkles" x-on:click="fill()" x-bind:disabled="busy">{{ __('Fill empty cells') }}</flux:button>
                    <flux:button size="sm" variant="primary" icon="check" x-on:click="save()" x-bind:disabled="busy || ! dirty">{{ __('Save') }}</flux:button>
                    <span x-show="dirty" x-cloak class="text-sm text-amber-700 dark:text-amber-300">{{ __('Unsaved changes') }}</span>
                </div>
            @else
                <flux:text>{{ $roster->isApproved() ? __('Approved: this plan counts for attendance.') : __('Waiting for approval: the grid cannot be changed.') }}</flux:text>
            @endif

            <div class="flex flex-wrap gap-2">
                <flux:button size="sm" icon="arrow-down-tray" :href="route('payroll.attendance.rosters.export', $roster->id)">{{ __('Export to Excel') }}</flux:button>
                @if ($editable)
                    <flux:modal.trigger name="roster-import">
                        <flux:button size="sm" icon="arrow-up-tray">{{ __('Import from Excel') }}</flux:button>
                    </flux:modal.trigger>
                    <flux:button size="sm" variant="primary" icon="paper-airplane" x-on:click="needsSave(() => $dispatch('confirm', {
                        title: @js(__('Send for approval?')),
                        message: @js(__('You cannot change the roster while it is waiting for approval.')),
                        confirmText: @js(__('Send')),
                        onConfirm: () => $wire.submit(),
                    }))">{{ $roster->approvalStatus() ? __('Send again') : __('Send for approval') }}</flux:button>
                @elseif ($roster->isApproved())
                    <flux:button size="sm" variant="primary" icon="document-duplicate" x-on:click="$dispatch('confirm', {
                        title: @js(__('Plan a change?')),
                        message: @js(__('A copy of this roster is made as a new draft. Change it and send it for approval; once approved, it replaces this plan.')),
                        confirmText: @js(__('Make a copy')),
                        onConfirm: () => $wire.planChange(),
                    })">{{ __('Plan a change') }}</flux:button>
                @endif
            </div>
        </div>
    </x-ui.card>

    {{-- Grid --}}
    @if ($employees->isEmpty())
        <x-ui.empty icon="users" :heading="__('No employees')" :description="__('This org unit (and the units below it) has no active employees you can see.')" />
    @else
        <x-ui.card :padding="false">
            <div wire:ignore class="max-h-[70vh] overflow-auto select-none">
                <table class="border-separate border-spacing-0 text-xs">
                    <thead class="sticky top-0 z-20">
                        <tr>
                            <th class="sticky left-0 z-30 min-w-48 border-b border-zinc-200 bg-white px-3 py-2 text-left font-medium dark:border-white/10 dark:bg-zinc-900">{{ __('Employee') }}</th>
                            @foreach ($dates as $date)
                                <th class="min-w-11 border-b border-zinc-200 bg-white px-1 py-1 text-center font-medium dark:border-white/10 dark:bg-zinc-900 {{ $date->isWeekend() ? 'text-ember-700 dark:text-ember-300' : '' }}">
                                    <button type="button" class="w-full rounded px-1 hover:bg-zinc-100 disabled:cursor-default disabled:hover:bg-transparent dark:hover:bg-white/10"
                                        x-on:click="fillCol(@js($date->toDateString()))" x-bind:disabled="! editable"
                                        title="{{ $editable ? __('Fill this day with the brush') : format_date($date) }}">
                                        <div class="text-[10px] uppercase text-zinc-500">{{ $date->translatedFormat('D') }}</div>
                                        <div>{{ $date->day }}</div>
                                    </button>
                                </th>
                            @endforeach
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($employees as $employee)
                            <tr wire:key="row-{{ $employee->id }}">
                                <th class="sticky left-0 z-10 border-b border-zinc-100 bg-white px-3 py-1 text-left font-normal dark:border-white/5 dark:bg-zinc-900">
                                    <button type="button" class="text-left hover:underline disabled:no-underline" x-on:click="fillRow({{ $employee->id }})" x-bind:disabled="! editable"
                                        title="{{ $editable ? __('Fill this row with the brush') : '' }}">
                                        <span class="font-medium">{{ $employee->code }}</span> · {{ $employee->name }}
                                    </button>
                                </th>
                                @foreach ($dates as $date)
                                    @php $d = $date->toDateString(); @endphp
                                    <td class="border-b border-zinc-100 p-0.5 dark:border-white/5">
                                        <div class="flex h-7 items-center justify-center rounded font-medium"
                                            x-bind:class="[cls(cells[{{ $employee->id }}]['{{ $d }}']), editable ? 'cursor-pointer hover:ring-1 hover:ring-brand-500' : '']"
                                            x-bind:title="title(cells[{{ $employee->id }}]['{{ $d }}'])"
                                            x-text="label(cells[{{ $employee->id }}]['{{ $d }}'])"
                                            x-on:mousedown.prevent="painting = true; paint({{ $employee->id }}, '{{ $d }}')"
                                            x-on:mouseenter="painting && paint({{ $employee->id }}, '{{ $d }}')"></div>
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </x-ui.card>

        <div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-zinc-500">
            @foreach ($shifts as $shift)
                <span><span class="font-medium text-zinc-700 dark:text-zinc-200">{{ $shift->code }}</span> {{ $shift->optionLabel() }}</span>
            @endforeach
            <span><span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('Off') }}</span> {{ __('day off') }}</span>
            <span><span class="font-medium text-zinc-700 dark:text-zinc-200">{{ __('OC') }}</span> {{ __('on call') }}</span>
            <span>{{ __('Empty: not planned (own or default shift applies)') }}</span>
        </div>
    @endif

    {{-- Import --}}
    @if ($editable)
        <x-ui.modal name="roster-import" :heading="__('Import from Excel')">
            <form wire:submit="import" id="roster-import-form" class="space-y-4">
                <flux:text class="text-sm">{{ __('Use "Export to Excel" as the template: change the cells and upload the file. Type a shift code, OFF or CALL; leave a cell empty to clear it. Employees and days not in the file stay as they are.') }}</flux:text>
                <x-form.file wire:model="file" :label="__('Excel file')" accept=".xlsx,.xls,.csv" />

                @if ($importProblems)
                    <flux:callout variant="danger" icon="exclamation-triangle" :heading="__('Nothing was imported. Fix these and upload again:')">
                        <flux:callout.text>
                            <ul class="list-disc space-y-0.5 ps-4">
                                @foreach (array_slice($importProblems, 0, 20) as $problem)
                                    <li>{{ $problem }}</li>
                                @endforeach
                            </ul>
                            @if (count($importProblems) > 20)
                                <p class="mt-1">{{ __('… and :count more.', ['count' => count($importProblems) - 20]) }}</p>
                            @endif
                        </flux:callout.text>
                    </flux:callout>
                @endif
            </form>

            <x-slot:footer>
                <flux:button type="submit" form="roster-import-form" variant="primary" icon="arrow-up-tray">{{ __('Import') }}</flux:button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
</div>
