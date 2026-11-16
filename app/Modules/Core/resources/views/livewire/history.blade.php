{{-- History tab: newest change first. --}}
<div class="space-y-4">
    @forelse ($entries as $entry)
        <div class="flex gap-3" wire:key="entry-{{ $loop->index }}">
            <flux:avatar size="sm" :name="$entry['who']" />

            <div class="min-w-0 flex-1 space-y-2">
                <div class="text-sm">
                    <span class="font-medium">{{ $entry['who'] }}</span>
                    <span class="text-zinc-500">{{ $entry['event'] }}</span>
                    <span class="text-zinc-400" title="{{ format_datetime($entry['when']) }}">· {{ $entry['when']?->diffForHumans() }}</span>
                </div>

                @if ($entry['changes'])
                    <div class="overflow-hidden rounded-lg border border-zinc-200 text-sm dark:border-white/10">
                        @foreach ($entry['changes'] as $change)
                            <div class="grid grid-cols-[8rem_1fr] gap-3 border-b border-zinc-100 px-3 py-1.5 last:border-0 dark:border-white/5">
                                <span class="text-zinc-500">{{ $change['field'] }}</span>
                                <span class="break-words">
                                    @if ($change['old'] !== '—' || $entry['event'] !== __('created this'))
                                        <span class="text-zinc-400 line-through">{{ $change['old'] }}</span>
                                        <span class="text-zinc-400">→</span>
                                    @endif
                                    <span>{{ $change['new'] }}</span>
                                </span>
                            </div>
                        @endforeach
                    </div>
                @endif
            </div>
        </div>
    @empty
        <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
            <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                <flux:icon name="clock" />
            </div>
            <flux:heading size="lg" class="mt-4">{{ __('No history yet') }}</flux:heading>
            <flux:text class="mt-1 max-w-sm">{{ __('Changes to this record will appear here.') }}</flux:text>
        </div>
    @endforelse

    @if ($hasMore)
        <flux:button size="sm" variant="ghost" wire:click="showMore">{{ __('Show more') }}</flux:button>
    @endif
</div>
