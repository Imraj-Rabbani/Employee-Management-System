<div class="space-y-4">
    {{-- Toolbar: view switch, search, collapse --}}
    <div class="flex flex-wrap items-center gap-3">
        <div class="flex gap-1">
            <flux:button size="sm" :variant="$view === 'people' ? 'filled' : 'ghost'" icon="users" wire:click="$set('view', 'people')">{{ __('People') }}</flux:button>
            <flux:button size="sm" :variant="$view === 'positions' ? 'filled' : 'ghost'" icon="briefcase" wire:click="$set('view', 'positions')">{{ __('Positions') }}</flux:button>
        </div>

        <div class="relative w-72">
            <flux:input size="sm" icon="magnifying-glass" wire:model.live.debounce.300ms="search"
                :placeholder="$view === 'people' ? __('Find a person…') : __('Find a position or holder…')" />
            @if ($matches)
                <ul class="absolute z-20 mt-1 w-full overflow-hidden rounded-xl bg-white shadow-lg ring-1 ring-zinc-500/15 dark:bg-brand-900">
                    @foreach ($matches as $match)
                        <li>
                            <button type="button" wire:click="show({{ $match['id'] }})" class="block w-full px-3 py-2 text-left text-sm hover:bg-zinc-500/10">
                                <span class="font-medium">{{ $match['title'] }}</span>
                                <span class="text-zinc-500">· {{ $match['subtitle'] }}</span>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <flux:button size="sm" variant="ghost" icon="arrows-pointing-in" wire:click="collapseAll" class="ms-auto">{{ __('Collapse all') }}</flux:button>
    </div>

    <x-ui.card :padding="false">
        @if ($roots === [])
            <x-ui.empty :icon="$view === 'people' ? 'users' : 'briefcase'"
                :heading="$view === 'people' ? __('No employees to show') : __('No positions to show')"
                :description="$view === 'people' ? __('Set supervisors on the Job tab to build the chart.') : __('Add positions under Organization → Positions.')" />
        @else
            {{-- Wide charts scroll sideways inside the card, never the whole page. --}}
            <div class="overflow-x-auto p-6">
                <div class="org-chart mx-auto w-max">
                    <ul>
                        @foreach ($roots as $id)
                            @include('hrm::livewire.org-chart-node', ['id' => $id])
                        @endforeach
                    </ul>
                </div>
            </div>
        @endif
    </x-ui.card>

    {{-- Connector lines between the cards: plain CSS on nested lists (no library). --}}
    @once
        <style>
            .org-chart ul { position: relative; display: flex; justify-content: center; padding-top: 1.25rem; }
            .org-chart > ul { padding-top: 0; }
            .org-chart li { position: relative; list-style: none; padding: 1.25rem 0.375rem 0; text-align: center; }
            .org-chart li::before, .org-chart li::after {
                content: ''; position: absolute; top: 0; right: 50%; width: 50%; height: 1.25rem;
                border-top: 1px solid var(--org-line);
            }
            .org-chart li::after { right: auto; left: 50%; border-left: 1px solid var(--org-line); }
            .org-chart li:only-child::before, .org-chart li:only-child::after { display: none; }
            .org-chart li:only-child { padding-top: 0; }
            .org-chart li:first-child::before, .org-chart li:last-child::after { border: 0 none; }
            .org-chart li:last-child::before { border-right: 1px solid var(--org-line); border-radius: 0 6px 0 0; }
            .org-chart li:first-child::after { border-radius: 6px 0 0 0; }
            .org-chart ul ul::before {
                content: ''; position: absolute; top: 0; left: 50%; height: 1.25rem; border-left: 1px solid var(--org-line);
            }
            .org-chart > ul > li:only-child { padding-top: 0; }
            .org-chart { --org-line: rgb(161 161 170 / 0.6); }
            .dark .org-chart { --org-line: rgb(255 255 255 / 0.2); }
        </style>
    @endonce
</div>
