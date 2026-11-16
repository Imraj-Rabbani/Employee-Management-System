{{-- Setup → Users list. PHP side: App\Modules\Core\Livewire\UsersTable --}}
<div class="glass overflow-hidden rounded-2xl">

    {{-- Toolbar: search, status filter, export --}}
    <div class="flex flex-wrap items-center gap-3 border-b border-white/50 p-4 dark:border-white/10">
        <div class="w-full sm:w-72">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search…')" clearable />
        </div>

        <div class="w-full sm:w-44">
            <flux:select wire:model.live="status" :aria-label="__('Status')">
                <flux:select.option value="">{{ __('All') }}: {{ __('Status') }}</flux:select.option>
                <flux:select.option value="yes">{{ __('Verified') }}</flux:select.option>
                <flux:select.option value="no">{{ __('Not verified') }}</flux:select.option>
            </flux:select>
        </div>

        @if ($search !== '' || $status !== '')
            <flux:button wire:click="clearFilters" variant="ghost" size="sm" icon="x-mark">{{ __('Clear') }}</flux:button>
        @endif

        <flux:spacer />

        <flux:button wire:click="export" size="sm" icon="arrow-down-tray">{{ __('Export') }}</flux:button>
    </div>

    {{-- Bulk action bar: only while rows are ticked --}}
    @if ($canManage && count($selected) > 0)
        <div class="flex flex-wrap items-center gap-3 border-b border-white/50 bg-brand-800/5 px-4 py-2 text-sm dark:border-white/10 dark:bg-white/5">
            <span class="font-medium">{{ trans_choice(':count selected|:count selected', count($selected), ['count' => count($selected)]) }}</span>

            <flux:button size="sm" wire:click="runBulkAction('verify')">{{ __('Mark verified') }}</flux:button>

            <flux:button size="sm" variant="danger" x-on:click="$dispatch('confirm', {
                title: @js(__('Delete the selected users?')),
                confirmText: @js(__('Delete')),
                onConfirm: () => $wire.runBulkAction('delete'),
            })">{{ __('Delete') }}</flux:button>

            <flux:button wire:click="clearSelection" size="sm" variant="ghost">{{ __('Clear selection') }}</flux:button>
        </div>
    @endif

    {{-- The table --}}
    <div class="overflow-x-auto" wire:loading.class="opacity-60">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-white/50 text-left text-zinc-600 dark:border-white/10 dark:text-zinc-300">
                    @if ($canManage)
                        <th class="w-10 px-4 py-3">
                            <flux:checkbox wire:model.live="selectPage" :aria-label="__('Select all on this page')" />
                        </th>
                    @endif

                    {{-- A sortable header is a button; the arrow shows the current direction. --}}
                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('name')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Name') }}
                            @if ($sortBy === 'name')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('email')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Email') }}
                            @if ($sortBy === 'email')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium">{{ __('Role') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>

                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('created_at')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Joined') }}
                            @if ($sortBy === 'created_at')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>
                </tr>
            </thead>

            <tbody>
                @foreach ($rows as $user)
                    <tr wire:key="user-{{ $user->id }}" class="border-b border-white/40 text-zinc-800 last:border-0 hover:bg-white/40 dark:border-white/5 dark:text-zinc-100 dark:hover:bg-white/5">
                        @if ($canManage)
                            <td class="px-4 py-3">
                                <flux:checkbox wire:model.live="selected" value="{{ $user->id }}" :aria-label="__('Select row')" />
                            </td>
                        @endif

                        <td class="px-4 py-3">{{ $user->name }}</td>
                        <td class="px-4 py-3">{{ $user->email }}</td>

                        {{-- The role opens the "change role" form (UserRoleForm listens for edit-user-role). --}}
                        <td class="px-4 py-3">
                            @if ($canManage)
                                <button type="button" x-on:click="$dispatch('edit-user-role', { id: {{ $user->id }} })" class="text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">
                                    {{ $roleNames[$user->id] ?? __('No role') }}
                                </button>
                            @else
                                {{ $roleNames[$user->id] ?? __('No role') }}
                            @endif
                        </td>

                        <td class="px-4 py-3">
                            @if ($user->email_verified_at)
                                <x-ui.badge color="success" dot>{{ __('Verified') }}</x-ui.badge>
                            @else
                                <x-ui.badge color="warning" dot>{{ __('Not verified') }}</x-ui.badge>
                            @endif
                        </td>

                        <td class="px-4 py-3">{{ format_date($user->created_at) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if ($rows->isEmpty())
            <div class="px-6 py-12 text-center">
                <flux:heading size="lg">{{ __('No results') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Try a different search or clear the filters.') }}</flux:text>
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
