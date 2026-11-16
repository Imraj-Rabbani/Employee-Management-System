<div>
    <div class="glass overflow-hidden rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ __('Documents') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Red = past its expiry date, yellow = expiring within :days days.', ['days' => \App\Modules\Hrm\Models\EmployeeDocument::WARN_DAYS]) }}</flux:text>
            </div>
            @if ($canUpdate)
                <div class="flex shrink-0 items-center gap-2">
                    <flux:button size="sm" icon="plus" wire:click="add">{{ __('Add document') }}</flux:button>
                </div>
            @endif
        </div>

        @if ($rows->isEmpty())
            <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                    <flux:icon name="document-text" />
                </div>
                <flux:heading size="lg" class="mt-4">{{ __('No documents yet') }}</flux:heading>
            </div>
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
    </div>

    @if ($canUpdate)
        <div>
            <x-ui.modal :name="$modal" :heading="$record ? __('Edit document') : __('Add document')">
                <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('Type') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                            <flux:select aria-required="true" wire:model.live="form.type">
                                <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                                @foreach ($types as $optionValue => $optionLabel)
                                    <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="form.type" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Number') }}</flux:label>
                            <flux:input wire:model="form.number" />
                            <flux:error name="form.number" />
                        </flux:field>
                    </div>
                    @if ((string) ($form['type'] ?? '') === (string) $other)
                        <flux:field>
                            <flux:label>{{ __('Name of the document') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                            <flux:input aria-required="true" wire:model="form.title" />
                            <flux:error name="form.title" />
                        </flux:field>
                    @endif
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('Issue date') }}</flux:label>
                            <flux:input type="date" wire:model="form.issue_date" />
                            <flux:error name="form.issue_date" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Expiry date') }}</flux:label>
                            <flux:description>{{ __('Leave empty if it never expires.') }}</flux:description>
                            <flux:input type="date" wire:model="form.expiry_date" />
                            <flux:error name="form.expiry_date" />
                        </flux:field>
                    </div>
                    {{-- Attachment: the current file (download + Remove), then the input to add or replace it. --}}
                    <div class="space-y-2">
                        @if ($record?->attachment_path)
                            <div class="flex items-center gap-2 text-sm">
                                <flux:icon.paper-clip variant="micro" class="text-zinc-500" />
                                <a href="{{ route('hrm.employees.attachment', [$employee, $section, $record->id]) }}" class="font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $record->attachment_name ?? __('Attachment') }}</a>
                                <flux:button size="xs" variant="ghost" class="!text-red-600" x-on:click="$dispatch('confirm', {
                                    title: @js(__('Remove this file?')),
                                    confirmText: @js(__('Remove')),
                                    onConfirm: () => $wire.removeAttachment(),
                                })">{{ __('Remove') }}</flux:button>
                            </div>
                        @endif

                        <flux:field>
                            <flux:label>{{ $record?->attachment_path ? __('Replace scan') : __('Scan / copy') }}</flux:label>
                            <flux:description>{{ __('PDF, JPG, PNG or Word, max 5 MB.') }}</flux:description>
                            <flux:input type="file" wire:model="upload" accept=".pdf,.jpg,.jpeg,.png,.docx" />
                            <flux:text wire:loading wire:target="upload" class="mt-2 text-sm">{{ __('Uploading…') }}</flux:text>
                            <flux:error name="upload" />
                        </flux:field>
                    </div>

                    {{-- Delete: only when editing an existing record --}}
                    @if ($record)
                        <flux:separator />
                        <flux:button size="sm" variant="ghost" icon="trash" class="!text-red-600" x-on:click="$dispatch('confirm', {
                            title: @js(__('Delete this record?')),
                            message: @js(__('The document and its file are deleted.')),
                            confirmText: @js(__('Delete')),
                            onConfirm: () => $wire.delete(),
                        })">{{ __('Delete') }}</flux:button>
                    @endif
                </form>

                <x-slot:footer>
                    <flux:button type="submit" form="{{ $modal }}" variant="primary">{{ __('Save') }}</flux:button>
                </x-slot:footer>
            </x-ui.modal>
        </div>
    @endif
</div>
