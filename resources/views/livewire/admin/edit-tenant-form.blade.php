<div>
    <x-ui.modal name="edit-tenant" :heading="__('Edit company')" :subheading="$code ? __('Code: :code (cannot be changed)', ['code' => $code]) : null">
        <form wire:submit="save" class="space-y-6" id="edit-tenant-form">
            <flux:field>
                <flux:label>{{ __('Company name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:input aria-required="true" wire:model="name" />
                <flux:error name="name" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Plan') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:select aria-required="true" wire:model="plan">
                    @foreach (\App\Core\Enums\Plan::cases() as $option)
                        <flux:select.option :value="$option->value">{{ $option->label() }} — {{ $option->summary() }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="plan" />
            </flux:field>

            <div class="grid gap-4 sm:grid-cols-2 items-start">
                <flux:switch wire:model="isActive" :label="__('Active')" :description="__('Inactive companies cannot log in.')" />

                <flux:field>
                    <flux:label>{{ __('Subscription ends') }}</flux:label>
                    <flux:description>{{ __('Empty = no end date.') }}</flux:description>
                    <flux:input type="date" wire:model="expiresAt" />
                    <flux:error name="expiresAt" />
                </flux:field>
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
                        <flux:field>
                            <flux:label>{{ __($slot['label']) }}</flux:label>
                            @if ($moduleOff)
                                <flux:description>{{ __('Only used when :module is switched on.', ['module' => $moduleOptions[$slot['module']]]) }}</flux:description>
                            @endif
                            <flux:select wire:model="plugins.{{ $key }}">
                                <flux:select.option value="">{{ __('Default') }}</flux:select.option>
                                @foreach ($slot['options'] as $optionValue => $optionLabel)
                                    <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="plugins.{{ $key }}" />
                        </flux:field>
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
