{{-- The bell: unread count, opens the Notifications page. PHP side: App\Modules\Core\Livewire\NotificationBell --}}
<div wire:poll.60s>
    <flux:tooltip :content="__('Notifications')" position="bottom">
        <flux:button :href="route('core.notifications')" wire:navigate variant="subtle" square class="relative mr-1" :aria-label="$unread > 0 ? __('Notifications (:count unread)', ['count' => $unread]) : __('Notifications')">
            <flux:icon.bell variant="mini" />
            @if ($unread > 0)
                <span class="absolute -right-0.5 -top-0.5 flex min-w-4 items-center justify-center rounded-full bg-red-600 px-1 text-[10px] font-semibold leading-4 text-white">
                    {{ $unread > 9 ? '9+' : $unread }}
                </span>
            @endif
        </flux:button>
    </flux:tooltip>
</div>
