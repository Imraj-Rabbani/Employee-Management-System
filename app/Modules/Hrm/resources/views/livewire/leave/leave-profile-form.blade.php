{{-- New / Edit leave profile (LeaveProfileForm): name, and the days per year of each leave type ($days). --}}
<div>
    <x-ui.modal :name="$modal" :heading="$record ? __('Edit leave profile') : __('New leave profile')">
        <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
            <flux:field>
                <flux:label>{{ __('Name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:input :placeholder="__('e.g. Staff, Workers, Probation')" aria-required="true" wire:model="form.name" />
                <flux:error name="form.name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Detail') }}</flux:label>
                <flux:textarea wire:model="form.detail" rows="2" />
                <flux:error name="form.detail" />
            </flux:field>

            <flux:separator :text="__('Days per year')" />
            @if ($types->isEmpty())
                <flux:text class="text-sm">{{ __('Add leave types first (Leave types tab).') }}</flux:text>
            @else
                <flux:text class="text-sm">{{ __('Empty = this leave cannot be taken. 0 = may be taken without days, e.g. leave without pay.') }}</flux:text>
                <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($types as $type)
                        <div wire:key="leave-type-{{ $type->id }}" class="flex items-center justify-between gap-4 py-2">
                            <div class="min-w-0">
                                <div class="text-sm font-medium">{{ $type->optionLabel() }}</div>
                                <div class="text-xs text-zinc-500">
                                    {{ $type->earn_type->label() }}
                                    @unless ($type->is_active) · {{ __('Inactive') }} @endunless
                                </div>
                            </div>
                            <div class="w-28 shrink-0">
                                <flux:input type="number" step="0.5" min="0" size="sm" wire:model="days.{{ $type->id }}" :aria-label="$type->name" />
                            </div>
                        </div>
                        @error('days.'.$type->id)
                            <flux:text class="text-sm !text-red-600">{{ $message }}</flux:text>
                        @enderror
                    @endforeach
                </div>
            @endif

            <flux:switch wire:model="form.is_active" :label="__('Active')" />

            {{-- Delete: only when editing an existing record --}}
            @if ($record)
                <flux:separator />
                <flux:button size="sm" variant="ghost" icon="trash" class="!text-red-600" x-on:click="$dispatch('confirm', {
                    title: {{ Js::from(__('Delete this record?')) }},
                    message: {{ Js::from(__('Not possible while employees use it. Make it inactive instead.')) }},
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
