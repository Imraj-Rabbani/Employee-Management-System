<div class="mt-6 flex items-center gap-4">
    <flux:button wire:click="increment">Click me</flux:button>
    <flux:text>Clicked {{ $count }} times</flux:text>

    <flux:button wire:click="decrement">Click me</flux:button>
    <flux:text>Clicked {{ $count }} times</flux:text>

    <flux:button wire:click="save" variant="primary">Save (shows a toast)</flux:button>

    <flux:button variant="danger" x-on:click="$dispatch('confirm', {
        title: 'Reset the counter?',
        message: 'The count goes back to 0.',
        confirmText: 'Reset',
        onConfirm: () => $wire.resetCount(),
    })">Reset (asks first)</flux:button>
</div>

