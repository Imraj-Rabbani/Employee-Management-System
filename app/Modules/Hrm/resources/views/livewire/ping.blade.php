<div class="mt-6 flex items-center gap-4">
    <flux:button wire:click="increment">Click me</flux:button>
    <flux:text>Clicked {{ $count }} times</flux:text>

    <flux:button wire:click="decrement">Click me</flux:button>
    <flux:text>Clicked {{ $count }} times</flux:text>
</div>

