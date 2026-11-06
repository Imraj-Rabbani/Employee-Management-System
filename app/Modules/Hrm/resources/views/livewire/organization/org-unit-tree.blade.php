{{--
    Org unit tree (OrgUnitTree). Alpine state lives on the outer div:
      collapsed  ids the user folded away, remembered in the browser ($persist)
      dragging   id of the unit being dragged, over = id of the unit under the mouse
--}}
<div
    x-data="{
        collapsed: $persist([]).as('hrm-org-tree-collapsed'),
        dragging: null,
        over: null,
        isOpen(id) { return ! this.collapsed.includes(id) },
        toggle(id) { this.isOpen(id) ? this.collapsed.push(id) : this.collapsed = this.collapsed.filter(i => i !== id) },
        start(id, event) { this.dragging = id; event.dataTransfer.effectAllowed = 'move'; event.dataTransfer.setData('text/plain', id) },
        drop(target) {
            const id = this.dragging;
            this.dragging = null; this.over = null;
            if (id !== null && id !== target) { $wire.move(id, target) }
        },
    }"
>
    <x-ui.card :padding="false">
        <div class="flex flex-wrap items-center gap-2 border-b border-white/50 p-4 dark:border-white/10">
            <flux:text class="mr-auto text-sm">{{ trans_choice(':count unit|:count units', $total) }} · {{ __('Drag a unit onto another to move it.') }}</flux:text>
            <flux:button size="sm" variant="ghost" icon="chevron-down" x-on:click="collapsed = []">{{ __('Expand all') }}</flux:button>
            <flux:button size="sm" variant="ghost" icon="chevron-right" x-on:click="collapsed = @js($parentIds)">{{ __('Collapse all') }}</flux:button>
        </div>

        <div class="p-2">
            @if ($roots === [])
                <x-ui.empty icon="building-office-2" :heading="__('No org units yet')" :description="__('Start with your company as the top unit.')" />
            @else
                {{-- Drop here to make a unit top-level. Only visible while dragging. --}}
                <div x-show="dragging !== null" x-cloak
                    x-on:dragover.prevent="over = 'top'"
                    x-on:dragleave="over === 'top' && (over = null)"
                    x-on:drop.prevent="drop(null)"
                    x-bind:class="over === 'top' ? 'border-brand-600 bg-brand-500/10' : 'border-zinc-300 dark:border-white/20'"
                    class="mb-2 rounded-lg border-2 border-dashed px-3 py-2 text-center text-sm text-zinc-500">
                    {{ __('Drop here to make it a top-level unit') }}
                </div>

                <ul role="tree" class="space-y-0.5">
                    @foreach ($roots as $node)
                        @include('hrm::livewire.organization.org-unit-node', ['node' => $node, 'depth' => 0])
                    @endforeach
                </ul>
            @endif
        </div>
    </x-ui.card>
</div>
