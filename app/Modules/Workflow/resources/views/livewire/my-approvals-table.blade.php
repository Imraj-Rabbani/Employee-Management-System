{{-- MyApprovalsTable list. PHP side: App\Modules\Workflow\Livewire\MyApprovalsTable --}}
<div class="glass overflow-hidden rounded-2xl">

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-3 border-b border-white/50 p-4 dark:border-white/10">
        <div class="w-full sm:w-44">
            <flux:select wire:model.live="show" :aria-label="__('Show')">
                <flux:select.option value="">{{ __('All') }}: {{ __('Show') }}</flux:select.option>
                @foreach ($showOptions as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($show !== '')
            <flux:button wire:click="clearFilters" variant="ghost" size="sm" icon="x-mark">{{ __('Clear') }}</flux:button>
        @endif

        <flux:spacer />

        <flux:button wire:click="export" size="sm" icon="arrow-down-tray">{{ __('Export') }}</flux:button>
    </div>

    {{-- The table --}}
    <div class="overflow-x-auto" wire:loading.class="opacity-60">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-white/50 text-left text-zinc-600 dark:border-white/10 dark:text-zinc-300">
                    <th class="px-4 py-3 font-medium">{{ __('Type') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Subject') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Requested by') }}</th>
                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('level')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Level') }}
                            @if ($sortBy === 'level')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('created_at')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Asked') }}
                            @if ($sortBy === 'created_at')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($rows as $approval)
                    <tr wire:key="approval-{{ $approval->getKey() }}" class="border-b border-white/40 text-zinc-800 last:border-0 hover:bg-white/40 dark:border-white/5 dark:text-zinc-100 dark:hover:bg-white/5">
                        <td class="px-4 py-3">{{ $approval->step->workflow->approvalType->name }}</td>
                        <td class="px-4 py-3">
                            <button type="button" x-on:click="$dispatch('open-approval', { id: {{ $approval->id }} })" class="text-left font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">
                                {{ $this->subject($approval) }}
                            </button>
                        </td>
                        <td class="px-4 py-3">{{ $this->userName($approval->requester_user_id) }}</td>
                        <td class="px-4 py-3">{{ $approval->level }}</td>
                        <td class="px-4 py-3">{{ format_datetime($approval->created_at) }}</td>
                        <td class="px-4 py-3">
                            <x-ui.badge :color="$approval->status->color()">{{ $approval->status->label() }}</x-ui.badge>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if ($rows->isEmpty())
            <div class="px-6 py-12 text-center">
                <flux:heading size="lg">{{ __('No results') }}</flux:heading>
            </div>
        @endif
    </div>

    {{-- Footer: page size and page links --}}
    <div class="flex flex-wrap items-center gap-4 border-t border-white/50 px-6 py-4 text-sm text-zinc-600 dark:border-white/10 dark:text-zinc-300">
        <div class="flex items-center gap-2">
            <span>{{ __('Rows per page') }}</span>
            <div class="w-20">
                <flux:select wire:model.live="perPage" size="sm" :aria-label="__('Rows per page')">
                    <flux:select.option value="10">10</flux:select.option>
                    <flux:select.option value="25">25</flux:select.option>
                    <flux:select.option value="50">50</flux:select.option>
                    <flux:select.option value="100">100</flux:select.option>
                </flux:select>
            </div>
        </div>

        <div class="ms-auto">{{ $rows->links() }}</div>
    </div>
</div>
