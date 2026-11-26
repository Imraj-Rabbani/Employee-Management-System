<div>
    {{-- Invitations sent from here that nobody has accepted yet. Hidden when there are none. --}}
    @if ($pending->isNotEmpty())
        <div class="glass overflow-hidden rounded-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
                <div>
                    <flux:heading size="lg">{{ __('Pending invitations') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Invited, but not registered yet. They become employees when they complete the form in the email.') }}</flux:text>
                </div>
            </div>

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
                                title: {{ Js::from(__('Cancel this invitation?')) }},
                                message: {{ Js::from(__('The link in the email stops working.')) }},
                                confirmText: {{ Js::from(__('Cancel invitation')) }},
                                onConfirm: () => $wire.cancel({{ $invited->id }}),
                            })">{{ __('Cancel') }}</flux:button>
                        </div>
                    </li>
                @endforeach
            </ul>
        </div>
    @endif

    <x-ui.modal name="invite-user-form" :heading="__('Invite user')"
        :subheading="__('They get an email with a link to fill in their details and choose a password (valid :days days).', ['days' => \App\Modules\Core\Notifications\UserInvited::DAYS_VALID])">
        <form wire:submit="invite" id="invite-user-form" class="space-y-5">
            <flux:field>
                <flux:label>{{ __('Email') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:input aria-required="true" wire:model="email" type="email" />
                <flux:error name="email" />
            </flux:field>

            @if ($roles)
                <flux:field>
                    <flux:label>{{ __('Role') }}</flux:label>
                    <flux:description>{{ __('Decides which screens they can use. Check it below before sending.') }}</flux:description>
                    <flux:select wire:model.live="roleId">
                        <flux:select.option value="">{{ __('None: self service only') }}</flux:select.option>
                        @foreach ($roles as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="roleId" />
                </flux:field>

                {{-- What the chosen role allows, so the inviter can check before sending. --}}
                <div class="rounded-xl bg-zinc-500/5 px-4 py-3 text-sm">
                    <div class="flex items-center justify-between gap-2">
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('This role allows') }}</div>
                        @if ($canManageRoles)
                            <a href="{{ route('core.roles') }}" target="_blank" wire:navigate class="underline text-sm decoration-neutral-400 underline-offset-2 duration-300 ease-out hover:decoration-neutral-700 text-neutral-900 dark:text-neutral-200 dark:hover:decoration-neutral-100 text-xs">{{ __('Edit roles') }}</a>
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
