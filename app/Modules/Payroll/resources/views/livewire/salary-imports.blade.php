{{-- Payroll → Salary Setup → Import salaries (SalaryImports). Same layout as the employee import. --}}
<div class="space-y-4" @if ($busy) wire:poll.2s @endif>
    <div class="glass overflow-hidden rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ __('Import salaries from Excel') }}</flux:heading>
                <flux:text class="mt-1">{{ __('All or nothing: if any row has a problem, nothing is imported and you get the list of problems. Fix the file and upload it again.') }}</flux:text>
            </div>
        </div>

        <div class="p-6">
            <div class="grid gap-6 md:grid-cols-2">
                <div class="space-y-2">
                    <flux:heading>1. {{ __('Download the template') }}</flux:heading>
                    <flux:text>{{ __('It lists your employees. On the rows to change, fill “Effective from” and either Gross or Basic (the salary profile is applied; a filled head column overrides that head), or the head columns alone. Rows without “Effective from” are left out.') }}</flux:text>
                    <flux:button icon="arrow-down-tray" :href="route('payroll.setup.import.template')">{{ __('Download template') }}</flux:button>
                </div>

                <form wire:submit="start" class="space-y-3">
                    <flux:heading>2. {{ __('Upload the filled-in file') }}</flux:heading>
                    <flux:field>
                        <flux:label>{{ __('Excel file') }}</flux:label>
                        <flux:description>{{ __('At most :rows rows, 10 MB.', ['rows' => number_format(\App\Modules\Payroll\Imports\SalaryImporter::MAX_ROWS)]) }}</flux:description>
                        <flux:input type="file" wire:model="file" accept=".xlsx,.xls,.csv" />
                        <flux:text wire:loading wire:target="file" class="mt-2 text-sm">{{ __('Uploading…') }}</flux:text>
                        <flux:error name="file" />
                    </flux:field>
                    <flux:button type="submit" variant="primary" icon="arrow-up-tray">{{ __('Start import') }}</flux:button>
                </form>
            </div>
        </div>
    </div>

    <div class="glass overflow-hidden rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ __('Recent imports') }}</flux:heading>
            </div>
        </div>

        @if ($imports->isEmpty())
            <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                    <flux:icon name="arrow-up-tray" />
                </div>
                <flux:heading size="lg" class="mt-4">{{ __('No imports yet') }}</flux:heading>
            </div>
        @else
            <ul class="divide-y divide-white/50 dark:divide-white/10">
                @foreach ($imports as $import)
                    <li class="px-6 py-4" wire:key="import-{{ $import->id }}" x-data="{ open: @js($loop->first) }">
                        <div class="flex flex-wrap items-center gap-3">
                            <x-ui.badge :color="$import->status->color()">{{ $import->status->label() }}</x-ui.badge>
                            <span class="font-medium">{{ $import->file_name }}</span>
                            <span class="text-sm text-zinc-500">
                                {{ format_datetime($import->created_at) }} · {{ $import->user->name ?? __('unknown user') }}
                            </span>
                            <span class="ms-auto text-sm tabular-nums">
                                @if ($import->status === \App\Modules\Hrm\Enums\ImportStatus::Done)
                                    {{ trans_choice(':count salary imported|:count salaries imported', $import->imported_rows) }}
                                    @if ($import->total_rows > $import->imported_rows)
                                        · {{ trans_choice(':count row without “Effective from” left out|:count rows without “Effective from” left out', $import->total_rows - $import->imported_rows) }}
                                    @endif
                                @elseif ($import->status === \App\Modules\Hrm\Enums\ImportStatus::Failed)
                                    {{ trans_choice(':count problem|:count problems', count($import->errors ?? [])) }}
                                    @if ($import->total_rows)
                                        · {{ trans_choice(':count row checked|:count rows checked', $import->total_rows) }}
                                    @endif
                                    <flux:button size="xs" variant="ghost" x-on:click="open = ! open" x-text="open ? {{ Js::from(__('Hide')) }} : {{ Js::from(__('Show')) }}" />
                                @elseif ($import->status === $pending)
                                    {{ __('Waiting for the queue worker…') }}
                                @endif
                            </span>
                        </div>

                        @if ($import->status === \App\Modules\Hrm\Enums\ImportStatus::Failed && $import->errors)
                            <div x-show="open" x-cloak class="mt-3 max-h-96 overflow-auto rounded-xl ring-1 ring-zinc-500/10">
                                <table class="w-full text-sm">
                                    <thead class="sticky top-0 bg-zinc-50 text-left text-xs uppercase tracking-wide text-zinc-500 dark:bg-brand-900 dark:text-zinc-400">
                                        <tr>
                                            <th class="px-4 py-2 font-medium">{{ __('Row') }}</th>
                                            <th class="px-4 py-2 font-medium">{{ __('Column') }}</th>
                                            <th class="px-4 py-2 font-medium">{{ __('Problem') }}</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach (array_slice($import->errors, 0, 500) as $error)
                                            <tr class="border-t border-zinc-500/10">
                                                <td class="px-4 py-2 tabular-nums">{{ $error['row'] ?: '—' }}</td>
                                                <td class="px-4 py-2">{{ $headings[$error['column']] ?? ($error['column'] ?: '—') }}</td>
                                                <td class="px-4 py-2">{{ $error['message'] }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                                @if (count($import->errors) > 500)
                                    <flux:text class="px-4 py-2">{{ __('Showing the first 500 problems.') }}</flux:text>
                                @endif
                            </div>
                        @endif
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
