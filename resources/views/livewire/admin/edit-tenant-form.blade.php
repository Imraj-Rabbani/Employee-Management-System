<div>
    <x-ui.modal name="edit-tenant" :heading="__('Edit company')" :subheading="$code ? __('Code: :code (cannot be changed)', ['code' => $code]) : null">
        <form wire:submit="save" class="space-y-6" id="edit-tenant-form">
            <x-form.input wire:model="name" :label="__('Company name')" required />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.toggle wire:model="isActive" :label="__('Active')" :hint="__('Inactive companies cannot log in.')" />
                <x-form.date wire:model="expiresAt" :label="__('Subscription ends')" :hint="__('Empty = no end date.')" />
            </div>

            <flux:checkbox.group wire:model="modules" :label="__('Modules')" :description="__('Core and Self Service are always on.')">
                @foreach ($moduleOptions as $key => $label)
                    <flux:checkbox :value="$key" :label="$label" />
                @endforeach
            </flux:checkbox.group>
            <flux:error name="modules.*" />

            {{-- Plugins: per-company replacements for parts of the system (PluginRegistry). --}}
            <flux:separator :text="__('Plugins')" />

            <div class="space-y-4">
                @foreach ($pluginSlots as $key => $slot)
                    @php $moduleOff = $slot['module'] && ! in_array($slot['module'], $modules, true) && isset($moduleOptions[$slot['module']]); @endphp

                    @if ($slot['multiple'])
                        <flux:checkbox.group wire:model="plugins.{{ $key }}" :label="__($slot['label'])" :description="$moduleOff ? __('Only used when :module is switched on.', ['module' => $moduleOptions[$slot['module']]]) : null">
                            @forelse ($slot['options'] as $value => $label)
                                <flux:checkbox :value="$value" :label="$label" />
                            @empty
                                <flux:text size="sm">{{ __('No plugins available yet.') }}</flux:text>
                            @endforelse
                        </flux:checkbox.group>
                    @else
                        <x-form.select wire:model="plugins.{{ $key }}" :label="__($slot['label'])" :options="$slot['options']" :placeholder="__('Default')"
                            :hint="$moduleOff ? __('Only used when :module is switched on.', ['module' => $moduleOptions[$slot['module']]]) : null" />
                    @endif
                    <flux:error name="plugins.{{ $key }}.*" />
                @endforeach
            </div>
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="edit-tenant-form" variant="primary">{{ __('Save') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
