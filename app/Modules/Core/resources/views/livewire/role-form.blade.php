<div>
    <x-ui.modal name="role-form" size="lg" :heading="$roleId ? __('Edit role') : __('New role')">
        {{-- Tabs: Alpine keeps the open tab in "tab"; each pane below shows with x-show. History only when editing. --}}
        <div x-data="{ tab: 'details' }" wire:key="role-tabs-{{ $roleId ?? 'new' }}">
            <div class="mb-4 flex gap-1 border-b border-zinc-200 dark:border-white/10" role="tablist">
                <button type="button" role="tab" x-on:click="tab = 'details'" x-bind:aria-selected="tab === 'details'"
                    x-bind:class="tab === 'details' ? 'border-brand-800 text-brand-900 dark:border-white dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'"
                    class="-mb-px border-b-2 px-3 py-2 text-sm font-medium transition">{{ __('Details') }}</button>

                @if ($roleId)
                    <button type="button" role="tab" x-on:click="tab = 'history'" x-bind:aria-selected="tab === 'history'"
                        x-bind:class="tab === 'history' ? 'border-brand-800 text-brand-900 dark:border-white dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'"
                        class="-mb-px border-b-2 px-3 py-2 text-sm font-medium transition">{{ __('History') }}</button>
                @endif
            </div>

            <div x-show="tab === 'details'">
                <form wire:submit="save" id="role-form" class="space-y-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('Name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                            <flux:description>{{ __('e.g. HR Officer') }}</flux:description>
                            <flux:input aria-required="true" wire:model="name" />
                            <flux:error name="name" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Description') }}</flux:label>
                            <flux:input wire:model="description" />
                            <flux:error name="description" />
                        </flux:field>
                    </div>

                    @if ($isAdmin)
                        <x-ui.badge color="success">{{ __('Full access') }}</x-ui.badge>
                        <flux:text>{{ __('The Administrator role can do everything in this company. Its permissions cannot be limited.') }}</flux:text>
                    @else
                        <flux:error name="permissions.*" />

                        {{-- One block per module, one checkbox group per permission group. --}}
                        <div class="space-y-6">
                            @foreach ($modules as $moduleLabel => $groups)
                                <div wire:key="module-{{ $moduleLabel }}">
                                    <flux:heading size="sm" class="mb-3 uppercase tracking-wide text-zinc-500">{{ $moduleLabel }}</flux:heading>

                                    <div class="grid gap-6 sm:grid-cols-2">
                                        @foreach ($groups as $group => $permissionList)
                                            <flux:checkbox.group wire:model="permissions" :label="__($group)">
                                                @foreach ($permissionList as $key => $label)
                                                    <flux:checkbox :value="$key" :label="__($label)" />
                                                @endforeach
                                            </flux:checkbox.group>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if ($roleId && ! $isAdmin)
                        <flux:separator />
                        <flux:button size="sm" variant="ghost" icon="trash" class="!text-red-600" x-on:click="$dispatch('confirm', {
                            title: @js(__('Delete this role?')),
                            message: @js(__('Only possible when no user has this role.')),
                            confirmText: @js(__('Delete')),
                            onConfirm: () => $wire.delete(),
                        })">{{ __('Delete role') }}</flux:button>
                    @endif
                </form>
            </div>

            @if ($roleId)
                <div x-show="tab === 'history'" x-cloak>
                    <livewire:core::history :subject="$role" can="core.roles.manage" :key="'history-role-'.$roleId" />
                </div>
            @endif
        </div>

        <x-slot:footer>
            <flux:button type="submit" form="role-form" variant="primary">{{ __('Save') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
