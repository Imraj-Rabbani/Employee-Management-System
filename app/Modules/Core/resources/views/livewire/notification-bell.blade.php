<div wire:poll.60s>
    <flux:dropdown position="bottom" align="end">
        <flux:button variant="subtle" square class="relative mr-1" :aria-label="__('Notifications')">
            <flux:icon.bell variant="mini" />
            @if ($unread > 0)
                <span class="absolute -right-0.5 -top-0.5 flex min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold leading-4 text-white">
                    {{ $unread > 9 ? '9+' : $unread }}
                </span>
            @endif
        </flux:button>

        <flux:menu class="w-80">
            <div class="flex items-center justify-between px-2 py-1.5">
                <span class="text-sm font-medium">{{ __('Notifications') }}</span>
                @if ($unread > 0)
                    <button type="button" wire:click="markAllRead" class="text-xs text-brand-800 hover:underline dark:text-brand-200">{{ __('Mark all as read') }}</button>
                @endif
            </div>
            <flux:menu.separator />

            @forelse ($latest as $notification)
                <flux:menu.item wire:click="open('{{ $notification->id }}')" wire:key="n-{{ $notification->id }}">
                    <div class="flex gap-2 py-0.5">
                        <span @class(['mt-1.5 size-2 shrink-0 rounded-full', 'bg-brand-600' => $notification->read_at === null, 'bg-transparent' => $notification->read_at !== null])></span>
                        <div class="min-w-0">
                            <div @class(['whitespace-normal text-sm', 'font-medium' => $notification->read_at === null])>{{ $this->textFor($notification) }}</div>
                            <div class="text-xs text-zinc-400">{{ $notification->created_at?->diffForHumans() }}</div>
                        </div>
                    </div>
                </flux:menu.item>
            @empty
                <div class="px-2 py-4 text-center text-sm text-zinc-500">{{ __('No notifications') }}</div>
            @endforelse
        </flux:menu>
    </flux:dropdown>
</div>
