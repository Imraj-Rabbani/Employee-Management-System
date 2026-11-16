<div>
    <div class="glass overflow-hidden rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ __('Education') }}</flux:heading>
            </div>
            @if ($canUpdate)
                <div class="flex shrink-0 items-center gap-2">
                    <flux:button size="sm" icon="plus" wire:click="add">{{ __('Add education') }}</flux:button>
                </div>
            @endif
        </div>

        @if ($rows->isEmpty())
            <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                    <flux:icon name="academic-cap" />
                </div>
                <flux:heading size="lg" class="mt-4">{{ __('No education added yet') }}</flux:heading>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    <tr class="border-b border-white/50 dark:border-white/10">
                        <th class="px-6 py-3 font-medium">{{ __('Degree') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Institution') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Subject') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Result') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Year') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Certificate') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-white/50 last:border-0 dark:border-white/10" wire:key="education-{{ $row->id }}">
                            <td class="px-6 py-3">
                                @if ($canUpdate)
                                    <button type="button" wire:click="edit({{ $row->id }})" class="text-left font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $row->degree }}</button>
                                @else
                                    <span class="font-medium">{{ $row->degree }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-3">{{ $row->institution ?? '—' }}</td>
                            <td class="px-6 py-3">{{ $row->subject ?? '—' }}</td>
                            <td class="px-6 py-3 tabular-nums">{{ $row->result() ?? '—' }}</td>
                            <td class="px-6 py-3 tabular-nums">{{ $row->passing_year ?? '—' }}</td>
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
            <x-ui.modal :name="$modal" size="lg" :heading="$record ? __('Edit education') : __('Add education')">
                <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('Degree / exam') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                            <flux:description>{{ __('e.g. SSC, HSC, B Sc (Hon\'s), MBA') }}</flux:description>
                            <flux:input aria-required="true" wire:model="form.degree" list="degree-suggestions" />
                            <flux:error name="form.degree" />
                        </flux:field>
                        <datalist id="degree-suggestions">
                            @foreach ($degreeSuggestions as $degree)
                                <option value="{{ $degree }}"></option>
                            @endforeach
                        </datalist>
                        <flux:field>
                            <flux:label>{{ __('Subject / group') }}</flux:label>
                            <flux:input wire:model="form.subject" />
                            <flux:error name="form.subject" />
                        </flux:field>
                    </div>
                    <flux:field>
                        <flux:label>{{ __('Institution') }}</flux:label>
                        <flux:input wire:model="form.institution" />
                        <flux:error name="form.institution" />
                    </flux:field>
                    <div class="grid gap-4 sm:grid-cols-3">
                        <flux:field>
                            <flux:label>{{ __('Score') }}</flux:label>
                            <flux:description>{{ __('GPA, CGPA or %') }}</flux:description>
                            <flux:input wire:model="form.score" type="number" step="0.01" min="0" />
                            <flux:error name="form.score" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Out of') }}</flux:label>
                            <flux:description>{{ __('e.g. 5, 4 or 100') }}</flux:description>
                            <flux:input wire:model="form.out_of" type="number" step="0.01" min="0" />
                            <flux:error name="form.out_of" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Passing year') }}</flux:label>
                            <flux:input wire:model="form.passing_year" type="number" min="1950" :max="now()->year + 1" />
                            <flux:error name="form.passing_year" />
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
                            <flux:label>{{ $record?->attachment_path ? __('Replace certificate') : __('Certificate') }}</flux:label>
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
                            message: @js(__('The row and its certificate file are deleted.')),
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
