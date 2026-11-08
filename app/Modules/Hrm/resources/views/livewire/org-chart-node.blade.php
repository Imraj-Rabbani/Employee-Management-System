{{-- One card of the org chart and, if opened, the cards below it. Included by org-chart and by itself. --}}
@php
    $node = $nodes[$id];
    $children = $byParent[$id] ?? [];
    $isOpen = in_array($id, $open, true);
@endphp
<li wire:key="node-{{ $view }}-{{ $id }}">
    <div @class([
        'inline-flex w-52 flex-col items-center gap-1 rounded-xl px-3 py-3 text-center ring-1 transition',
        'bg-white/70 ring-zinc-500/15 dark:bg-white/5' => $focus !== $id,
        'bg-brand-500/10 ring-2 ring-brand-600' => $focus === $id,
        'border border-dashed border-zinc-400/60 !bg-transparent' => $node['vacant'],
    ])>
        @if (! $node['vacant'])
            <flux:avatar size="sm" :name="$view === 'people' ? $node['title'] : $node['subtitle']" :src="$node['photo']" />
        @endif

        @if ($node['url'] && $view === 'people')
            <a href="{{ $node['url'] }}" wire:navigate class="w-full truncate text-sm font-medium text-brand-900 hover:underline dark:text-white">{{ $node['title'] }}</a>
        @else
            <span class="w-full truncate text-sm font-medium">{{ $node['title'] }}</span>
        @endif

        @if ($node['url'] && $view === 'positions')
            <a href="{{ $node['url'] }}" wire:navigate class="w-full truncate text-xs text-brand-800 hover:underline dark:text-brand-200">{{ $node['subtitle'] }}</a>
        @else
            <span @class(['w-full truncate text-xs', 'text-zinc-500' => ! $node['vacant'], 'font-medium text-amber-600' => $node['vacant']])>{{ $node['subtitle'] }}</span>
        @endif

        @if ($children)
            <button type="button" wire:click="toggle({{ $id }})" class="mt-1 inline-flex items-center gap-0.5 rounded-full bg-zinc-500/10 px-2 py-0.5 text-xs text-zinc-600 hover:bg-zinc-500/20 dark:text-zinc-300"
                aria-expanded="{{ $isOpen ? 'true' : 'false' }}"
                title="{{ $isOpen ? __('Hide the people below') : __('Show the people below') }}">
                <flux:icon :name="$isOpen ? 'chevron-up' : 'chevron-down'" variant="micro" />
                {{ count($children) }}
            </button>
        @endif
    </div>

    @if ($children && $isOpen)
        <ul>
            @foreach ($children as $child)
                @include('hrm::livewire.org-chart-node', ['id' => $child])
            @endforeach
        </ul>
    @endif
</li>
