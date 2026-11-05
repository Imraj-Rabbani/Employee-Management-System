<div>
    <x-ui.modal name="user-role-form" :heading="__('Change role')" :subheading="$userName">
        <form wire:submit="save" id="user-role-form" class="space-y-6">
            <x-form.select wire:model="roleId" :label="__('Role')" :options="$roles" :placeholder="__('Choose…')" required />
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="user-role-form" variant="primary">{{ __('Save') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
