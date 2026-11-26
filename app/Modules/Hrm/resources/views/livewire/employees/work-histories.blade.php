<div>
    <div class="glass overflow-hidden rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ __('Earlier jobs') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Work experience before joining this company.') }}</flux:text>
            </div>
            @if ($canUpdate)
                <div class="flex shrink-0 items-center gap-2">
                    <flux:button size="sm" icon="plus" wire:click="add">{{ __('Add job') }}</flux:button>
                </div>
            @endif
        </div>

        @if ($rows->isEmpty())
            <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                    <flux:icon name="briefcase" />
                </div>
                <flux:heading size="lg" class="mt-4">{{ __('No earlier jobs added yet') }}</flux:heading>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    <tr class="border-b border-white/50 dark:border-white/10">
                        <th class="px-6 py-3 font-medium">{{ __('Organization') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Designation') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('From') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('To') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Duration') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Letter') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-white/50 last:border-0 dark:border-white/10" wire:key="work-{{ $row->id }}">
                            <td class="px-6 py-3">
                                @if ($canUpdate)
                                    <button type="button" wire:click="edit({{ $row->id }})" class="text-left font-medium text-brand-800 underline-offset-4 dark:text-brand-200 underline decoration-current/50 hover:decoration-current hover:text-blue-600 dark:hover:text-blue-400">{{ $row->organization }}</button>
                                @else
                                    <span class="font-medium">{{ $row->organization }}</span>
                                @endif
                                @if ($row->leaving_reason)
                                    <div class="text-xs text-zinc-500">{{ $row->leaving_reason }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-3">{{ $row->designation ?? '—' }}</td>
                            <td class="px-6 py-3">{{ format_date($row->from_date) ?: '—' }}</td>
                            <td class="px-6 py-3">{{ format_date($row->to_date) ?: '—' }}</td>
                            <td class="px-6 py-3">{{ $row->duration() ?? '—' }}</td>
                            <td class="px-6 py-3">
                                @if ($row->attachment_path)
                                    <a href="{{ route('hrm.employees.attachment', [$employee, $section, $row->id]) }}" class="inline-flex items-center gap-1 text-brand-800 dark:text-brand-200 underline decoration-current/50 hover:decoration-current underline-offset-4 hover:text-blue-600 dark:hover:text-blue-400">
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
            <x-ui.modal :name="$modal" size="lg" :heading="$record ? __('Edit job') : __('Add job')">
                <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('Organization') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                            <flux:input aria-required="true" wire:model="form.organization" />
                            <flux:error name="form.organization" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Designation') }}</flux:label>
                            <flux:input wire:model="form.designation" />
                            <flux:error name="form.designation" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('From') }}</flux:label>
                            <flux:input type="date" wire:model="form.from_date" />
                            <flux:error name="form.from_date" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('To') }}</flux:label>
                            <flux:input type="date" wire:model="form.to_date" />
                            <flux:error name="form.to_date" />
                        </flux:field>
                    </div>
                    <flux:field>
                        <flux:label>{{ __('Reason for leaving') }}</flux:label>
                        <flux:input wire:model="form.leaving_reason" />
                        <flux:error name="form.leaving_reason" />
                    </flux:field>
                    {{-- Attachment: the current file (download + Remove), then the input to add or replace it. --}}
                    <div class="space-y-2">
                        @if ($record?->attachment_path)
                            <div class="flex items-center gap-2 text-sm">
                                <flux:icon.paper-clip variant="micro" class="text-zinc-500" />
                                <a href="{{ route('hrm.employees.attachment', [$employee, $section, $record->id]) }}" class="font-medium text-brand-800 underline-offset-4 dark:text-brand-200 underline decoration-current/50 hover:decoration-current hover:text-blue-600 dark:hover:text-blue-400">{{ $record->attachment_name ?? __('Attachment') }}</a>
                                <flux:button size="xs" variant="ghost" class="!text-red-600" x-on:click="$dispatch('confirm', {
                                    title: {{ Js::from(__('Remove this file?')) }},
                                    confirmText: {{ Js::from(__('Remove')) }},
                                    onConfirm: () => $wire.removeAttachment(),
                                })">{{ __('Remove') }}</flux:button>
                            </div>
                        @endif

                        <flux:field>
                            <flux:label>{{ $record?->attachment_path ? __('Replace experience letter') : __('Experience letter') }}</flux:label>
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
                            title: {{ Js::from(__('Delete this record?')) }},
                            message: {{ Js::from(__('The row and its letter file are deleted.')) }},
                            confirmText: {{ Js::from(__('Delete')) }},
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
