{{-- New / Edit leave profile (LeaveProfileForm): name, and the days per year of each leave type ($days). --}}
<x-form.lookup-modal :$modal :$record :heading="$record ? __('Edit leave profile') : __('New leave profile')"
    :deleteMessage="__('Not possible while employees use it. Make it inactive instead.')">
    <x-form.input wire:model="form.name" :label="__('Name')" required :hint="__('e.g. Staff, Workers, Probation')" />
    <x-form.textarea wire:model="form.detail" :label="__('Detail')" rows="2" />

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

    <x-form.toggle wire:model="form.is_active" :label="__('Active')" />
</x-form.lookup-modal>
