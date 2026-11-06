<div>
    <x-ui.modal name="role-form" size="lg" :heading="$roleId ? __('Edit role') : __('New role')">
        <x-ui.tabs :tabs="$roleId ? ['details' => __('Details'), 'history' => __('History')] : ['details' => __('Details')]" wire:key="role-tabs-{{ $roleId ?? 'new' }}">
            <div x-show="tab === 'details'">
                <form wire:submit="save" id="role-form" class="space-y-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-form.input wire:model="name" :label="__('Name')" :hint="__('e.g. HR Officer')" required />
                        <x-form.input wire:model="description" :label="__('Description')" />
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
        </x-ui.tabs>

        <x-slot:footer>
            <flux:button type="submit" form="role-form" variant="primary">{{ __('Save') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
