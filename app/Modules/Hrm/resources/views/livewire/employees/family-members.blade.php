<div>
    <div class="glass overflow-hidden rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ __('Family members') }}</flux:heading>
            </div>
            @if ($canUpdate)
                <div class="flex shrink-0 items-center gap-2">
                    <flux:button size="sm" icon="plus" wire:click="add">{{ __('Add family member') }}</flux:button>
                </div>
            @endif
        </div>

        @if ($rows->isEmpty())
            <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                    <flux:icon name="users" />
                </div>
                <flux:heading size="lg" class="mt-4">{{ __('No family members yet') }}</flux:heading>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    <tr class="border-b border-white/50 dark:border-white/10">
                        <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Relation') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Date of birth') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Gender') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Occupation') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Phone') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-white/50 last:border-0 dark:border-white/10" wire:key="family-{{ $row->id }}">
                            <td class="px-6 py-3">
                                @if ($canUpdate)
                                    <button type="button" wire:click="edit({{ $row->id }})" class="text-left font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $row->name }}</button>
                                @else
                                    <span class="font-medium">{{ $row->name }}</span>
                                @endif
                                @if ($row->name_local)
                                    <div class="text-xs text-zinc-500">{{ $row->name_local }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-3">{{ $row->relation?->label() ?? '—' }}</td>
                            <td class="px-6 py-3">
                                {{ format_date($row->birth_date) ?: '—' }}
                                @if ($row->birth_date)
                                    <span class="text-xs text-zinc-500">({{ trans_choice(':count year|:count years', (int) $row->birth_date->diffInYears(today())) }})</span>
                                @endif
                            </td>
                            <td class="px-6 py-3">{{ $row->gender?->label() ?? '—' }}</td>
                            <td class="px-6 py-3">{{ $row->occupation ?? '—' }}</td>
                            <td class="px-6 py-3">{{ $row->phone ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if ($canUpdate)
        <div>
            <x-ui.modal :name="$modal" size="lg" :heading="$record ? __('Edit family member') : __('Add family member')">
                <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('Relation') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                            <flux:select aria-required="true" wire:model="form.relation">
                                <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                                @foreach ($relations as $optionValue => $optionLabel)
                                    <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="form.relation" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                            <flux:input aria-required="true" wire:model="form.name" />
                            <flux:error name="form.name" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Name (local)') }}</flux:label>
                            <flux:input wire:model="form.name_local" />
                            <flux:error name="form.name_local" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Date of birth') }}</flux:label>
                            <flux:input type="date" wire:model="form.birth_date" />
                            <flux:error name="form.birth_date" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Gender') }}</flux:label>
                            <flux:select wire:model="form.gender">
                                <flux:select.option value="">{{ __('Not set') }}</flux:select.option>
                                @foreach ($genders as $optionValue => $optionLabel)
                                    <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="form.gender" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Occupation') }}</flux:label>
                            <flux:input wire:model="form.occupation" />
                            <flux:error name="form.occupation" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Phone') }}</flux:label>
                            <flux:input wire:model="form.phone" type="tel" />
                            <flux:error name="form.phone" />
                        </flux:field>
                    </div>

                    {{-- Delete: only when editing an existing record --}}
                    @if ($record)
                        <flux:separator />
                        <flux:button size="sm" variant="ghost" icon="trash" class="!text-red-600" x-on:click="$dispatch('confirm', {
                            title: @js(__('Delete this record?')),
                            message: @js(__('The family member is removed from this employee.')),
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
