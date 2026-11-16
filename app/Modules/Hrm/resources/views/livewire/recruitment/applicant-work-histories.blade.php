<div>
    <div class="glass overflow-hidden rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ __('Work history') }}</flux:heading>
            </div>
            <div class="flex shrink-0 items-center gap-2">
                <flux:button size="sm" icon="plus" wire:click="add">{{ __('Add job') }}</flux:button>
            </div>
        </div>

        @if ($rows->isEmpty())
            <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                    <flux:icon name="briefcase" />
                </div>
                <flux:heading size="lg" class="mt-4">{{ __('No work history added yet') }}</flux:heading>
            </div>
        @else
            <ul class="divide-y divide-white/50 dark:divide-white/10">
                @foreach ($rows as $row)
                    <li class="px-6 py-3 text-sm" wire:key="work-{{ $row->id }}">
                        <button type="button" wire:click="edit({{ $row->id }})" class="text-left font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $row->organization }}</button>
                        <span class="text-zinc-500">{{ $row->designation ? '· '.$row->designation : '' }}</span>
                        <div class="text-xs text-zinc-500">
                            {{ $row->from_date ? format_date($row->from_date) : '?' }} – {{ $row->to_date ? format_date($row->to_date) : __('now') }}
                            @if ($row->leaving_reason) · {{ $row->leaving_reason }} @endif
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>

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
                        <flux:description>{{ __('Empty = still working there') }}</flux:description>
                        <flux:input type="date" wire:model="form.to_date" />
                        <flux:error name="form.to_date" />
                    </flux:field>
                </div>
                <flux:field>
                    <flux:label>{{ __('Reason for leaving') }}</flux:label>
                    <flux:input wire:model="form.leaving_reason" />
                    <flux:error name="form.leaving_reason" />
                </flux:field>

                {{-- Delete: only when editing an existing record --}}
                @if ($record)
                    <flux:separator />
                    <flux:button size="sm" variant="ghost" icon="trash" class="!text-red-600" x-on:click="$dispatch('confirm', {
                        title: @js(__('Delete this record?')),
                        message: @js(__('This row is deleted.')),
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
</div>
