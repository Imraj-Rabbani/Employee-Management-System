<div>
    {{-- Invitations sent from here that nobody has accepted yet. Hidden when there are none. --}}
    @if ($pending->isNotEmpty())
        <x-ui.card :heading="__('Pending invitations')" :subheading="__('Invited, but not registered yet. They become employees when they complete the form in the email.')" :padding="false">
            <ul class="divide-y divide-zinc-200/60 dark:divide-white/10">
                @foreach ($pending as $invited)
                    <li class="flex flex-wrap items-center justify-between gap-3 px-6 py-3 text-sm" wire:key="invite-{{ $invited->id }}">
                        <div>
                            <div class="font-medium text-zinc-900 dark:text-zinc-100">{{ $invited->email }}</div>
                            <div class="mt-1 flex flex-wrap items-center gap-1">
                                <x-ui.badge :color="$invited->role() ? 'brand' : 'neutral'">{{ $invited->role()->name ?? __('Self service only') }}</x-ui.badge>
                                <span class="text-xs text-zinc-500">{{ __('Invited :date', ['date' => format_date($invited->invited_at)]) }}</span>
                            </div>
                        </div>
                        <div class="flex gap-1">
                            <flux:button size="xs" icon="envelope" wire:click="resend({{ $invited->id }})">{{ __('Resend') }}</flux:button>
                            <flux:button size="xs" variant="ghost" icon="x-mark" x-on:click="$dispatch('confirm', {
                                title: @js(__('Cancel this invitation?')),
                                message: @js(__('The link in the email stops working.')),
                                confirmText: @js(__('Cancel invitation')),
                                onConfirm: () => $wire.cancel({{ $invited->id }}),
                            })">{{ __('Cancel') }}</flux:button>
                        </div>
                    </li>
                @endforeach
            </ul>
        </x-ui.card>
    @endif

    <x-ui.modal name="invite-user-form" :heading="__('Invite user')"
        :subheading="__('They get an email with a link to fill in their details and choose a password (valid :days days).', ['days' => \App\Modules\Core\Notifications\UserInvited::DAYS_VALID])">
        <form wire:submit="invite" id="invite-user-form" class="space-y-5">
            <x-form.input wire:model="email" type="email" :label="__('Email')" required />

            @if ($roles)
                <x-form.select wire:model.live="roleId" :label="__('Role')" :options="$roles" :placeholder="__('None: self service only')"
                    :hint="__('Decides which screens they can use. Check it below before sending.')" />

                {{-- What the chosen role allows, so the inviter can check before sending. --}}
                <div class="rounded-xl bg-zinc-500/5 px-4 py-3 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('This role allows') }}</div>
                        @if ($canManageRoles)
                            <x-text-link class="text-xs" :href="route('core.roles')" target="_blank">{{ __('Edit roles') }}</x-text-link>
                        @endif
                    </div>

                    @if ($preview === null)
                        <p class="mt-1 text-zinc-600 dark:text-zinc-400">{{ __('Only their own record (Self Service).') }}</p>
                    @elseif ($preview === [])
                        <p class="mt-1 text-zinc-600 dark:text-zinc-400">{{ __('Nothing yet: this role has no permissions. Only Self Service.') }}</p>
                    @else
                        <dl class="mt-2 max-h-56 space-y-2 overflow-y-auto">
                            @foreach ($preview as $group => $labels)
                                <div>
                                    <dt class="font-medium text-zinc-800 dark:text-zinc-200">{{ $group }}</dt>
                                    <dd class="text-zinc-600 dark:text-zinc-400">{{ implode(', ', $labels) }}</dd>
                                </div>
                            @endforeach
                        </dl>
                    @endif
                </div>
            @endif
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="invite-user-form" variant="primary" icon="paper-airplane">
                <span wire:loading.remove wire:target="invite">{{ __('Send invitation') }}</span>
                <span wire:loading wire:target="invite">{{ __('Sending…') }}</span>
            </flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
