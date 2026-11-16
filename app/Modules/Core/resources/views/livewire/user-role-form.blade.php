<div>
    <x-ui.modal name="user-role-form" :heading="__('Change role')" :subheading="$userName">
        <form wire:submit="save" id="user-role-form" class="space-y-6">
            <flux:field>
                <flux:label>{{ __('Role') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:select aria-required="true" wire:model="roleId">
                    <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                    @foreach ($roles as $optionValue => $optionLabel)
                        <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="roleId" />
            </flux:field>
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="user-role-form" variant="primary">{{ __('Save') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
