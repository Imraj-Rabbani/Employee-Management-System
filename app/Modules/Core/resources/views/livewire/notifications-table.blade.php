{{-- Notifications page: the user's full notification history. PHP side: App\Modules\Core\Livewire\NotificationsTable --}}
<div class="glass overflow-hidden rounded-2xl">

    {{-- Toolbar: actions on the left, Filter on the right. --}}
    @php $activeFilters = count(array_filter([$status !== ''])); @endphp
    <div class="flex flex-wrap items-center gap-2 border-b border-white/50 p-4 dark:border-white/10">
        <flux:button size="sm" icon="check" x-bind:disabled="$wire.selected.length === 0" wire:click="markSelectedRead">{{ __('Mark as read') }}</flux:button>
        <flux:button size="sm" icon="check-circle" wire:click="markAllRead" :disabled="$unread === 0">{{ __('Mark all as read') }}</flux:button>
        <span x-show="$wire.selected.length > 0" x-cloak class="text-xs text-zinc-500 dark:text-zinc-400" x-text="$wire.selected.length + ' ' + {{ Js::from(__('selected')) }}"></span>
        @if ($unread > 0)
            <x-ui.badge color="brand">{{ __(':count unread', ['count' => $unread]) }}</x-ui.badge>
        @endif

        <flux:spacer />

        <flux:modal.trigger name="notification-filters">
            <flux:button size="sm" icon="funnel">{{ __('Filter') }}{{ $activeFilters > 0 ? " ($activeFilters)" : '' }}</flux:button>
        </flux:modal.trigger>
    </div>

    {{-- The Filter form. wire:model (not .live): the list only changes when Apply is clicked. --}}
    <x-ui.modal name="notification-filters" :heading="__('Filter')">
        <flux:field>
            <flux:label>{{ __('Show') }}</flux:label>
            <flux:select wire:model="status">
                <flux:select.option value="">{{ __('All') }}</flux:select.option>
                <flux:select.option value="unread">{{ __('Unread') }}</flux:select.option>
                <flux:select.option value="read">{{ __('Read') }}</flux:select.option>
            </flux:select>
        </flux:field>

        <x-slot:footer>
            <flux:button wire:click="clearFilters" variant="ghost">{{ __('Clear') }}</flux:button>
            <flux:button wire:click="applyFilters" variant="primary">{{ __('Apply') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>

    {{-- The table --}}
    <div class="overflow-x-auto" wire:loading.class="opacity-60">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-white/50 text-left text-zinc-600 dark:border-white/10 dark:text-zinc-300">
                    {{-- Tick all rows of this page (adds to / removes from $wire.selected). --}}
                    <th class="w-10 px-4 py-3" x-data="{ ids: {{ Js::from($rows->map(fn ($row) => (string) $row->getKey())->values()) }} }">
                        <input type="checkbox" class="size-4 rounded border-zinc-300 text-brand-800 focus:ring-brand-700 dark:border-white/20 dark:bg-white/10" aria-label="{{ __('Tick all on this page') }}"
                            x-bind:checked="ids.length > 0 && ids.every(id => $wire.selected.includes(id))"
                            x-on:change="$wire.selected = $event.target.checked ? [...new Set([...$wire.selected, ...ids])] : $wire.selected.filter(id => ! ids.includes(id))">
                    </th>
                    <th class="px-4 py-3 font-medium">{{ __('Notification') }}</th>
                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Received') }}
                            <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                        </button>
                    </th>
                </tr>
            </thead>

            <tbody>
                @foreach ($rows as $notification)
                    @php $isUnread = $notification->read_at === null; @endphp
                    <tr wire:key="notification-{{ $notification->id }}" x-bind:class="$wire.selected.includes('{{ $notification->id }}') && '!bg-brand-50/70 dark:!bg-white/10'" class="border-b border-white/40 text-zinc-800 last:border-0 hover:bg-white/40 dark:border-white/5 dark:text-zinc-100 dark:hover:bg-white/5">
                        <td class="px-4 py-3 align-top">
                            <input type="checkbox" wire:model="selected" value="{{ $notification->id }}" class="size-4 rounded border-zinc-300 text-brand-800 focus:ring-brand-700 dark:border-white/20 dark:bg-white/10" aria-label="{{ __('Tick this row') }}">
                        </td>
                        {{-- Opens what it is about (and marks it read). Unread ones are bold with a dot. --}}
                        <td class="px-4 py-3">
                            <button type="button" wire:click="open('{{ $notification->id }}')" class="flex items-start gap-2 text-left underline-offset-4 underline decoration-current/50 hover:decoration-current hover:text-blue-600 dark:hover:text-blue-400">
                                <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-brand-600 dark:bg-brand-300' => $isUnread, 'bg-transparent' => ! $isUnread])>
                                    @if ($isUnread)<span class="sr-only">{{ __('Unread') }}</span>@endif
                                </span>
                                <span @class(['font-semibold text-zinc-900 dark:text-white' => $isUnread, 'text-zinc-600 dark:text-zinc-300' => ! $isUnread])>{{ $presenter->text($notification) }}</span>
                            </button>
                        </td>
                        <td class="px-4 py-3 whitespace-nowrap text-zinc-500 dark:text-zinc-400" title="{{ $notification->created_at?->format('d M Y, H:i') }}">
                            {{ $notification->created_at?->diffForHumans() }}
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if ($rows->isEmpty())
            <div class="px-6 py-12 text-center">
                <flux:heading size="lg">{{ $status === '' ? __('No notifications yet') : __('No results') }}</flux:heading>
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
