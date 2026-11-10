{{-- One unit and (recursively) its sub-units. Included by org-unit-tree and by itself. --}}
@php
    /** @var \App\Modules\Hrm\Models\OrgUnit $unit */
    $unit = $node['unit'];
    $hasChildren = $node['children'] !== [];
@endphp
<li role="treeitem" wire:key="unit-{{ $unit->id }}" @if ($hasChildren) x-bind:aria-expanded="isOpen({{ $unit->id }})" @endif>
    <div
        draggable="true"
        x-on:dragstart.stop="start({{ $unit->id }}, $event)"
        x-on:dragend="dragging = null; over = null"
        x-on:dragover.prevent="over = {{ $unit->id }}"
        x-on:dragleave="over === {{ $unit->id }} && (over = null)"
        x-on:drop.prevent.stop="drop({{ $unit->id }})"
        x-bind:class="{
            'ring-2 ring-brand-600 bg-brand-500/10': over === {{ $unit->id }} && dragging !== {{ $unit->id }},
            'opacity-40': dragging === {{ $unit->id }},
        }"
        class="group flex cursor-grab items-center gap-2 rounded-lg py-1.5 pr-2 hover:bg-zinc-100 dark:hover:bg-white/5"
        style="padding-left: {{ 0.5 + $depth * 1.5 }}rem"
    >
        @if ($hasChildren)
            <button type="button" x-on:click="toggle({{ $unit->id }})" class="text-zinc-400 hover:text-zinc-700 dark:hover:text-zinc-200"
                x-bind:aria-label="isOpen({{ $unit->id }}) ? @js(__('Collapse')) : @js(__('Expand'))">
                <flux:icon.chevron-right variant="micro" x-bind:class="isOpen({{ $unit->id }}) && 'rotate-90'" class="transition-transform" />
            </button>
        @else
            <span class="w-4"></span>
        @endif

        <button type="button" x-on:click="$dispatch('edit-org-unit', { id: {{ $unit->id }} })"
            @class(['truncate text-left text-sm font-medium hover:underline', 'text-zinc-400 line-through' => ! $unit->is_active])>
            {{ $unit->name }}
        </button>

        <x-ui.badge class="shrink-0">{{ $unit->type->name }}</x-ui.badge>
        @if ($unit->code)
            <span class="shrink-0 text-xs text-zinc-400">{{ $unit->code }}</span>
        @endif
        @if ($unit->positions_count)
            <span class="shrink-0 text-xs text-zinc-400">· {{ trans_choice(':count position|:count positions', $unit->positions_count) }}</span>
        @endif

        <flux:button size="xs" variant="ghost" icon="plus" class="ml-auto opacity-0 group-hover:opacity-100 focus:opacity-100"
            x-on:click="$dispatch('create-org-unit', { preset: { parent_id: {{ $unit->id }} } })">{{ __('Sub-unit') }}</flux:button>
    </div>

    @if ($hasChildren)
        <ul role="group" x-show="isOpen({{ $unit->id }})" x-collapse class="space-y-0.5">
            @foreach ($node['children'] as $child)
                @include('hrm::livewire.organization.org-unit-node', ['node' => $child, 'depth' => $depth + 1])
            @endforeach
        </ul>
    @endif
</li>
