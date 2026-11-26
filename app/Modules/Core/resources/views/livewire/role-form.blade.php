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
                            <flux:input :placeholder="__('e.g. HR Officer')" aria-required="true" wire:model="name" />
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

                        {{--
                            One collapsible row per module. Everything is plain checkboxes bound to $wire.permissions
                            (the Livewire $permissions array), so the module checkbox and the single ones stay in sync.
                              - module checkbox: ticks / unticks every permission of the module
                                (half-ticked "indeterminate" when only some are ticked)
                              - arrow: shows the module's single permissions, closed at first
                        --}}
                        <div class="divide-y divide-zinc-200 rounded-lg border border-zinc-200 dark:divide-white/10 dark:border-white/10">
                            @foreach ($modules as $moduleLabel => $groups)
                                @php($moduleKeys = array_keys(array_merge(...array_values($groups))))

                                <div wire:key="module-{{ $moduleLabel }}"
                                    x-data="{
                                        open: false,
                                        keys: @js($moduleKeys),
                                        ticked() { return this.keys.filter(k => $wire.permissions.includes(k)).length },
                                        toggleAll(on) {
                                            const others = $wire.permissions.filter(k => ! this.keys.includes(k));
                                            $wire.permissions = on ? [...others, ...this.keys] : others;
                                        },
                                    }">
                                    <div class="flex items-center gap-3 px-3 py-2.5">
                                        <input type="checkbox" id="module-{{ $loop->index }}"
                                            class="size-4 rounded border-zinc-300 text-brand-800 focus:ring-brand-700 dark:border-white/20 dark:bg-white/10"
                                            x-bind:checked="ticked() === keys.length"
                                            x-bind:indeterminate="ticked() > 0 && ticked() < keys.length"
                                            x-on:change="toggleAll($event.target.checked)">
                                        <label for="module-{{ $loop->index }}" class="text-sm font-medium text-zinc-800 dark:text-white">{{ $moduleLabel }}</label>

                                        <button type="button" x-on:click="open = ! open" x-bind:aria-expanded="open"
                                            aria-label="{{ __('Show the permissions of :module', ['module' => $moduleLabel]) }}"
                                            class="ms-auto flex items-center gap-2 rounded px-1.5 py-1 text-xs text-zinc-500 hover:bg-zinc-100 hover:text-zinc-800 dark:hover:bg-white/10 dark:hover:text-zinc-200">
                                            <span x-text="ticked() + ' / ' + keys.length"></span>
                                            <flux:icon.chevron-down variant="micro" class="transition-transform duration-200" x-bind:class="open ? 'rotate-180' : ''" />
                                        </button>
                                    </div>

                                    <div x-show="open" x-collapse x-cloak class="grid gap-5 border-t border-zinc-200 bg-zinc-50/60 px-3 py-4 sm:grid-cols-2 dark:border-white/10 dark:bg-white/5">
                                        @foreach ($groups as $group => $permissionList)
                                            <fieldset>
                                                <legend class="mb-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ __($group) }}</legend>
                                                <div class="space-y-2">
                                                    @foreach ($permissionList as $key => $label)
                                                        <label class="flex items-start gap-2 text-sm text-zinc-700 dark:text-zinc-300">
                                                            <input type="checkbox" value="{{ $key }}" x-model="$wire.permissions"
                                                                class="mt-0.5 size-4 rounded border-zinc-300 text-brand-800 focus:ring-brand-700 dark:border-white/20 dark:bg-white/10">
                                                            <span>{{ __($label) }}</span>
                                                        </label>
                                                    @endforeach
                                                </div>
                                            </fieldset>
                                        @endforeach
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @endif

                    @if ($roleId && ! $isAdmin)
                        <flux:separator />
                        <flux:button size="sm" variant="ghost" icon="trash" class="!text-red-600" x-on:click="$dispatch('confirm', {
                            title: {{ Js::from(__('Delete this role?')) }},
                            message: {{ Js::from(__('Only possible when no user has this role.')) }},
                            confirmText: {{ Js::from(__('Delete')) }},
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
