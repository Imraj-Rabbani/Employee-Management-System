<div>
    <div class="glass overflow-hidden rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ __('Education') }}</flux:heading>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <flux:button size="sm" icon="plus" wire:click="add">{{ __('Add education') }}</flux:button>
            </div>
        </div>

        @if ($rows->isEmpty())
            <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                    <flux:icon name="academic-cap" />
                </div>
                <flux:heading size="lg" class="mt-4">{{ __('No education added yet') }}</flux:heading>
            </div>
        @else
            <ul class="divide-y divide-white/50 dark:divide-white/10">
                @foreach ($rows as $row)
                    <li class="px-6 py-3 text-sm" wire:key="edu-{{ $row->id }}">
                        <button type="button" wire:click="edit({{ $row->id }})" class="text-left font-medium text-brand-800 underline-offset-4 dark:text-brand-200 underline decoration-current/50 hover:decoration-current hover:text-blue-600 dark:hover:text-blue-400">{{ $row->degree }}</button>
                        <span class="text-zinc-500">{{ $row->subject ? '· '.$row->subject : '' }}</span>
                        <div class="text-xs text-zinc-500">
                            {{ collect([$row->institution, $row->result(), $row->passing_year])->filter()->join(' · ') ?: '—' }}
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

    <div>
        <x-ui.modal :name="$modal" size="lg" :heading="$record ? __('Edit education') : __('Add education')">
            <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Degree / exam') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                        <flux:input :placeholder="__('e.g. SSC, HSC, B Sc (Hon\'s), MBA')" aria-required="true" wire:model="form.degree" />
                        <flux:error name="form.degree" />
                    </flux:field>

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
                        <flux:input :placeholder="__('e.g. 5, 4 or 100')" wire:model="form.out_of" type="number" step="0.01" min="0" />
                        <flux:error name="form.out_of" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Passing year') }}</flux:label>
                        <flux:input wire:model="form.passing_year" type="number" min="1950" :max="now()->year + 1" />
                        <flux:error name="form.passing_year" />
                    </flux:field>
                </div>

                {{-- Delete: only when editing an existing record --}}
                @if ($record)
                    <flux:separator />
                    <flux:button size="sm" variant="ghost" icon="trash" class="!text-red-600" x-on:click="$dispatch('confirm', {
                        title: {{ Js::from(__('Delete this record?')) }},
                        message: {{ Js::from(__('This row is deleted.')) }},
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
</div>
