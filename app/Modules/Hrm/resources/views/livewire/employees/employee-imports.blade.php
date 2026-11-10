<div class="space-y-4" @if ($busy) wire:poll.2s @endif>
    <x-ui.card :heading="__('Import employees from Excel')"
        :subheading="__('All or nothing: if any row has a problem, nothing is imported and you get the list of problems. Fix the file and upload it again.')">
        <div class="grid gap-6 md:grid-cols-2">
            <div class="space-y-2">
                <flux:heading>1. {{ __('Download the template') }}</flux:heading>
                <flux:text>{{ __('Fill in one employee per row and delete the example row. The “Lists” sheet shows the org units, designations, grades and types you can use. Columns with * are required.') }}</flux:text>
                <flux:button icon="arrow-down-tray" :href="route('hrm.employees.import.template')">{{ __('Download template') }}</flux:button>
            </div>

            <form wire:submit="start" class="space-y-3">
                <flux:heading>2. {{ __('Upload the filled-in file') }}</flux:heading>
                <x-form.file wire:model="file" :label="__('Excel file')" accept=".xlsx,.xls,.csv"
                    :hint="__('At most :rows rows, 10 MB.', ['rows' => number_format(\App\Modules\Hrm\Imports\EmployeeImporter::MAX_ROWS)])" />
                <flux:button type="submit" variant="primary" icon="arrow-up-tray">{{ __('Start import') }}</flux:button>
            </form>
        </div>
    </x-ui.card>

    <x-ui.card :heading="__('Recent imports')" :padding="false">
        @if ($imports->isEmpty())
            <x-ui.empty icon="arrow-up-tray" :heading="__('No imports yet')" />
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
                                    {{ trans_choice(':count employee imported|:count employees imported', $import->imported_rows) }}
                                @elseif ($import->status === \App\Modules\Hrm\Enums\ImportStatus::Failed)
                                    {{ trans_choice(':count problem|:count problems', count($import->errors ?? [])) }}
                                    @if ($import->total_rows)
                                        · {{ trans_choice(':count row checked|:count rows checked', $import->total_rows) }}
                                    @endif
                                    <flux:button size="xs" variant="ghost" x-on:click="open = ! open" x-text="open ? @js(__('Hide')) : @js(__('Show'))" />
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
    </x-ui.card>
</div>
