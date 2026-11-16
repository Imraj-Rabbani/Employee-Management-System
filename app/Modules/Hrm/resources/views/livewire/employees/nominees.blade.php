<div>
    <div class="glass overflow-hidden rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ __('Nominees') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Who receives the employee\'s benefits. The shares must add up to 100%.') }}</flux:text>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                @if ($rows->isNotEmpty())
                    <x-ui.badge :color="$complete ? 'success' : 'warning'">{{ __('Total :total%', ['total' => $total]) }}</x-ui.badge>
                @endif
                @if ($canUpdate)
                    <flux:button size="sm" icon="plus" wire:click="add">{{ __('Add nominee') }}</flux:button>
                @endif
            </div>
        </div>

        @if ($rows->isEmpty())
            <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                    <flux:icon name="user-group" />
                </div>
                <flux:heading size="lg" class="mt-4">{{ __('No nominees yet') }}</flux:heading>
            </div>
        @else
            @unless ($complete)
                <flux:callout variant="warning" icon="exclamation-triangle" class="m-4"
                    :heading="__('The shares add up to :total%, not 100%.', ['total' => $total])" />
            @endunless

            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    <tr class="border-b border-white/50 dark:border-white/10">
                        <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Relation') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('National ID') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Phone') }}</th>
                        <th class="px-6 py-3 text-right font-medium">{{ __('Share') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-white/50 last:border-0 dark:border-white/10" wire:key="nominee-{{ $row->id }}">
                            <td class="px-6 py-3">
                                @if ($canUpdate)
                                    <button type="button" wire:click="edit({{ $row->id }})" class="text-left font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $row->name }}</button>
                                @else
                                    <span class="font-medium">{{ $row->name }}</span>
                                @endif
                                @if ($row->is_minor)
                                    <div class="text-xs text-zinc-500">{{ __('Minor · guardian: :name', ['name' => $row->guardian['name'] ?? '—']) }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-3">{{ $row->relation?->label() ?? '—' }}</td>
                            <td class="px-6 py-3">{{ $row->national_id ?? '—' }}</td>
                            <td class="px-6 py-3">{{ $row->phone ?? '—' }}</td>
                            <td class="px-6 py-3 text-right tabular-nums">{{ rtrim(rtrim($row->share_percent, '0'), '.') }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if ($canUpdate)
        <div>
            <x-ui.modal :name="$modal" size="lg" :heading="$record ? __('Edit nominee') : __('Add nominee')">
                <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
                    @if ($family && ! $record)
                        <flux:field>
                            <flux:label>{{ __('Copy from family member') }}</flux:label>
                            <flux:select x-on:change="$event.target.value && $wire.copyFamily($event.target.value)">
                                <flux:select.option value="">{{ __('Choose to fill name and relation…') }}</flux:select.option>
                                @foreach ($family as $optionValue => $optionLabel)
                                    <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                                @endforeach
                            </flux:select>
                        </flux:field>
                    @endif

                    <div class="grid gap-4 sm:grid-cols-[2fr_1fr_1fr]">
                        <flux:field>
                            <flux:label>{{ __('Name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                            <flux:input aria-required="true" wire:model="form.name" />
                            <flux:error name="form.name" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Relation') }}</flux:label>
                            <flux:select wire:model="form.relation">
                                <flux:select.option value="">{{ __('Not set') }}</flux:select.option>
                                @foreach ($relations as $optionValue => $optionLabel)
                                    <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="form.relation" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Share %') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                            <flux:input aria-required="true" wire:model="form.share_percent" type="number" step="0.01" min="0.01" max="100" />
                            <flux:error name="form.share_percent" />
                        </flux:field>
                    </div>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('National ID') }}</flux:label>
                            <flux:input wire:model="form.national_id" />
                            <flux:error name="form.national_id" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Phone') }}</flux:label>
                            <flux:input wire:model="form.phone" type="tel" />
                            <flux:error name="form.phone" />
                        </flux:field>
                    </div>
                    <flux:field>
                        <flux:label>{{ __('Address') }}</flux:label>
                        <flux:textarea wire:model="form.address" rows="2" />
                        <flux:error name="form.address" />
                    </flux:field>

                    <flux:switch wire:model.live="form.is_minor" :label="__('Minor (under 18)')" :description="__('A minor needs a guardian.')" />
                    @if ($form['is_minor'] ?? false)
                        <div class="grid gap-4 rounded-xl bg-zinc-500/5 p-4 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>{{ __('Guardian name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                                <flux:input aria-required="true" wire:model="form.guardian_name" />
                                <flux:error name="form.guardian_name" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Guardian relation') }}</flux:label>
                                <flux:description>{{ __('to the nominee, e.g. Mother') }}</flux:description>
                                <flux:input wire:model="form.guardian_relation" />
                                <flux:error name="form.guardian_relation" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Guardian national ID') }}</flux:label>
                                <flux:input wire:model="form.guardian_national_id" />
                                <flux:error name="form.guardian_national_id" />
                            </flux:field>

                            <flux:field>
                                <flux:label>{{ __('Guardian phone') }}</flux:label>
                                <flux:input wire:model="form.guardian_phone" type="tel" />
                                <flux:error name="form.guardian_phone" />
                            </flux:field>
                        </div>
                    @endif

                    {{-- Delete: only when editing an existing record --}}
                    @if ($record)
                        <flux:separator />
                        <flux:button size="sm" variant="ghost" icon="trash" class="!text-red-600" x-on:click="$dispatch('confirm', {
                            title: @js(__('Delete this record?')),
                            message: @js(__('The nominee is removed. Remember to share their percentage among the others.')),
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
