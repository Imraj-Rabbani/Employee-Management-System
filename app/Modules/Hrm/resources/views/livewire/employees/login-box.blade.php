<div class="min-w-56 rounded-xl bg-zinc-500/5 px-4 py-3 text-sm">
    <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ __('Login') }}</div>

    @if ($user)
        <div class="mt-1 font-medium text-zinc-900 dark:text-zinc-100">{{ $user->email }}</div>
        <div class="mt-1 flex flex-wrap items-center gap-1">
            <x-ui.badge :color="$role ? 'brand' : 'neutral'">{{ $role->name ?? __('Self service only') }}</x-ui.badge>
            @if ($user->hasOpenInvitation())
                <x-ui.badge color="warning">{{ __('Invited :date, not accepted yet', ['date' => format_date($user->invited_at)]) }}</x-ui.badge>
            @endif
        </div>

        @if ($canManage)
            <div class="mt-2 flex gap-1">
                @if ($user->hasOpenInvitation())
                    <flux:button size="xs" icon="envelope" wire:click="resend">{{ __('Resend invitation') }}</flux:button>
                @endif
                <flux:button size="xs" variant="ghost" icon="link-slash" x-on:click="$dispatch('confirm', {
                    title: @js(__('Unlink this login?')),
                    message: @js(__('The person can still log in, but no longer sees this employee record as their own. The login itself is not deleted.')),
                    confirmText: @js(__('Unlink')),
                    onConfirm: () => $wire.unlink(),
                })">{{ __('Unlink') }}</flux:button>
            </div>
        @endif
    @else
        <div class="mt-1 text-zinc-500">{{ __('No login') }}</div>
        @if ($canManage)
            <flux:button size="xs" variant="primary" icon="user-plus" class="mt-2" wire:click="open">{{ __('Give login') }}</flux:button>
        @endif
    @endif

    @if ($canManage && ! $user)
        <x-ui.modal name="employee-login-form" :heading="__('Give :name a login', ['name' => $employee->name])">
            <div class="flex gap-1 border-b border-zinc-200 dark:border-white/10" role="tablist">
                @foreach (['invite' => __('Invite by email'), 'link' => __('Link existing login')] as $key => $label)
                    <button type="button" role="tab" wire:click="$set('mode', '{{ $key }}')" @class([
                        '-mb-px border-b-2 px-3 py-2 text-sm font-medium transition',
                        'border-brand-800 text-brand-900 dark:border-white dark:text-white' => $mode === $key,
                        'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $mode !== $key,
                    ])>{{ $label }}</button>
                @endforeach
            </div>

            @if ($mode === 'invite')
                <form wire:submit="invite" id="employee-login-form" class="space-y-5">
                    <x-form.input wire:model="email" type="email" :label="__('Email')" required
                        :hint="__('They get an email with a link to set their password (valid :days days).', ['days' => \App\Modules\Core\Notifications\UserInvited::DAYS_VALID])" />
                    @if ($roles)
                        <x-form.select wire:model="roleId" :label="__('Role')" :options="$roles" :placeholder="__('None: self service only')"
                            :hint="__('Without a role they only see their own record.')" />
                    @endif
                </form>
            @else
                <form wire:submit="link" id="employee-login-form" class="space-y-5">
                    @if ($freeLogins)
                        <x-form.select wire:model="userId" :label="__('Login')" :options="$freeLogins" :placeholder="__('Choose…')" required
                            :hint="__('Logins of this company that do not belong to an employee yet.')" />
                    @else
                        <flux:text>{{ __('Every login of this company already belongs to an employee. Use “Invite by email”.') }}</flux:text>
                    @endif
                </form>
            @endif

            <x-slot:footer>
                <flux:button type="submit" form="employee-login-form" variant="primary">
                    {{ $mode === 'invite' ? __('Send invitation') : __('Link login') }}
                </flux:button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
