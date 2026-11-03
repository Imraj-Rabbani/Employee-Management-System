{{--
    "Nothing here yet" message for empty lists.

    <x-ui.empty icon="users" heading="No employees yet" description="Add your first employee to get started.">
        <flux:button variant="primary">Add employee</flux:button>
    </x-ui.empty>
--}}
@props([
    'icon' => 'inbox',
    'heading',
    'description' => null,
])

<div {{ $attributes->class('flex flex-col items-center justify-center px-6 py-12 text-center') }}>
    <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
        <flux:icon :name="$icon" />
    </div>

    <flux:heading size="lg" class="mt-4">{{ $heading }}</flux:heading>

    @if ($description)
        <flux:text class="mt-1 max-w-sm">{{ $description }}</flux:text>
    @endif

    @if ($slot->isNotEmpty())
        <div class="mt-6 flex items-center gap-2">{{ $slot }}</div>
    @endif
</div>
