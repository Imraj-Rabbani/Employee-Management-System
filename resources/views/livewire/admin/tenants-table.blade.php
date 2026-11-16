{{-- Platform admin → Companies list. PHP side: App\Livewire\Admin\TenantsTable --}}
<div class="glass overflow-hidden rounded-2xl">

    {{-- Toolbar: search, status filter, export --}}
    <div class="flex flex-wrap items-center gap-3 border-b border-white/50 p-4 dark:border-white/10">
        <div class="w-full sm:w-72">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search…')" clearable />
        </div>

        <div class="w-full sm:w-44">
            <flux:select wire:model.live="status" :aria-label="__('Status')">
                <flux:select.option value="">{{ __('All') }}: {{ __('Status') }}</flux:select.option>
                <flux:select.option value="active">{{ __('Active') }}</flux:select.option>
                <flux:select.option value="inactive">{{ __('Inactive') }}</flux:select.option>
            </flux:select>
        </div>

        @if ($search !== '' || $status !== '')
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
                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('code')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Code') }}
                            @if ($sortBy === 'code')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('name')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Company') }}
                            @if ($sortBy === 'name')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium">{{ __('Database') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Modules') }}</th>

                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('users_count')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Users') }}
                            @if ($sortBy === 'users_count')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>

                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('created_at')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Created') }}
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
                @foreach ($rows as $tenant)
                    <tr wire:key="tenant-{{ $tenant->id }}" class="border-b border-white/40 text-zinc-800 last:border-0 hover:bg-white/40 dark:border-white/5 dark:text-zinc-100 dark:hover:bg-white/5">
                        {{-- The code opens the Edit form (EditTenantForm listens for edit-tenant). --}}
                        <td class="px-4 py-3">
                            <button type="button" x-on:click="$dispatch('edit-tenant', { id: {{ $tenant->id }} })" class="font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200" title="{{ __('Edit') }}">
                                {{ $tenant->code }}
                            </button>
                        </td>

                        <td class="px-4 py-3">{{ $tenant->name }}</td>
                        <td class="px-4 py-3">{{ $tenant->database }}</td>
                        <td class="px-4 py-3">{{ $this->moduleNames($tenant) }}</td>
                        <td class="px-4 py-3">{{ $tenant->users_count }}</td>

                        <td class="px-4 py-3">
                            @if ($tenant->is_active)
                                <x-ui.badge color="success" dot>{{ __('Active') }}</x-ui.badge>
                            @else
                                <x-ui.badge color="danger" dot>{{ __('Inactive') }}</x-ui.badge>
                            @endif
                        </td>

                        <td class="px-4 py-3">{{ format_date($tenant->created_at) }}</td>
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
