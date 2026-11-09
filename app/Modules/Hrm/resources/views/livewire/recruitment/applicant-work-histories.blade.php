<div>
    <x-ui.card :heading="__('Work history')" :padding="false">
        <x-slot:actions>
            <flux:button size="sm" icon="plus" wire:click="add">{{ __('Add job') }}</flux:button>
        </x-slot:actions>

        @if ($rows->isEmpty())
            <x-ui.empty icon="briefcase" :heading="__('No work history added yet')" />
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
    </x-ui.card>

    <x-form.lookup-modal :$modal :$record size="lg" :heading="$record ? __('Edit job') : __('Add job')"
        :delete-message="__('This row is deleted.')">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.input wire:model="form.organization" :label="__('Organization')" required />
            <x-form.input wire:model="form.designation" :label="__('Designation')" />
            <x-form.date wire:model="form.from_date" :label="__('From')" />
            <x-form.date wire:model="form.to_date" :label="__('To')" :hint="__('Empty = still working there')" />
        </div>
        <x-form.input wire:model="form.leaving_reason" :label="__('Reason for leaving')" />
    </x-form.lookup-modal>
</div>
