<div>
    <x-ui.card :heading="__('Documents')" :subheading="__('Red = past its expiry date, yellow = expiring within :days days.', ['days' => \App\Modules\Hrm\Models\EmployeeDocument::WARN_DAYS])" :padding="false">
        @if ($canUpdate)
            <x-slot:actions>
                <flux:button size="sm" icon="plus" wire:click="add">{{ __('Add document') }}</flux:button>
            </x-slot:actions>
        @endif

        @if ($rows->isEmpty())
            <x-ui.empty icon="document-text" :heading="__('No documents yet')" />
        @else
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    <tr class="border-b border-white/50 dark:border-white/10">
                        <th class="px-6 py-3 font-medium">{{ __('Document') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Number') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Issued') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Expires') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('File') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        @php $days = $row->daysToExpiry(); @endphp
                        <tr class="border-b border-white/50 last:border-0 dark:border-white/10" wire:key="document-{{ $row->id }}">
                            <td class="px-6 py-3">
                                @if ($canUpdate)
                                    <button type="button" wire:click="edit({{ $row->id }})" class="text-left font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $row->name() }}</button>
                                @else
                                    <span class="font-medium">{{ $row->name() }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-3 tabular-nums">{{ $row->number ?? '—' }}</td>
                            <td class="px-6 py-3">{{ format_date($row->issue_date) ?: '—' }}</td>
                            <td class="px-6 py-3">
                                @if ($days === null)
                                    <span class="text-zinc-400">{{ __('Never') }}</span>
                                @else
                                    {{ format_date($row->expiry_date) }}
                                    @if ($days < 0)
                                        <x-ui.badge color="danger" class="ms-1">{{ __('Expired') }}</x-ui.badge>
                                    @elseif ($days <= \App\Modules\Hrm\Models\EmployeeDocument::WARN_DAYS)
                                        <x-ui.badge color="warning" class="ms-1">{{ $days === 0 ? __('Expires today') : trans_choice('Expires in :count day|Expires in :count days', $days) }}</x-ui.badge>
                                    @endif
                                @endif
                            </td>
                            <td class="px-6 py-3">
                                @if ($row->attachment_path)
                                    <a href="{{ route('hrm.employees.attachment', [$employee, $section, $row->id]) }}" class="inline-flex items-center gap-1 text-brand-800 hover:underline dark:text-brand-200">
                                        <flux:icon.paper-clip variant="micro" /> {{ __('Download') }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    @if ($canUpdate)
        <x-form.lookup-modal :$modal :$record :heading="$record ? __('Edit document') : __('Add document')"
            :delete-message="__('The document and its file are deleted.')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.select wire:model.live="form.type" :label="__('Type')" :options="$types" :placeholder="__('Choose…')" required />
                <x-form.input wire:model="form.number" :label="__('Number')" />
            </div>
            @if ((string) ($form['type'] ?? '') === (string) $other)
                <x-form.input wire:model="form.title" :label="__('Name of the document')" required />
            @endif
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.date wire:model="form.issue_date" :label="__('Issue date')" />
                <x-form.date wire:model="form.expiry_date" :label="__('Expiry date')" :hint="__('Leave empty if it never expires.')" />
            </div>
            <x-form.attachment :$record :label="$record?->attachment_path ? __('Replace scan') : __('Scan / copy')"
                :url="$record ? route('hrm.employees.attachment', [$employee, $section, $record->id]) : null" />
        </x-form.lookup-modal>
    @endif
</div>
