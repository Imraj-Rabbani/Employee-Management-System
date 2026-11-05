<div>
    <x-ui.modal name="create-tenant" :heading="__('New company')" :subheading="__('Creates the company, its own database, and its first admin login.')">
        <form wire:submit="save" class="space-y-6" id="create-tenant-form">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-form.input wire:model.blur="code" :label="__('Code')" :hint="__('e.g. sss. Cannot be changed later.')" required />
                <div class="sm:col-span-2">
                    <x-form.input wire:model="name" :label="__('Company name')" required />
                </div>
            </div>

            <flux:checkbox.group wire:model="modules" :label="__('Modules')" :description="__('Core and Self Service are always on.')">
                @foreach ($moduleOptions as $key => $label)
                    <flux:checkbox :value="$key" :label="$label" />
                @endforeach
            </flux:checkbox.group>

            <flux:separator :text="__('First admin login')" />

            <x-form.input wire:model="adminName" :label="__('Name')" required />
            <x-form.input wire:model="adminEmail" type="email" :label="__('Email')" required />
            <x-form.input wire:model="adminPassword" type="password" :label="__('Password')" :hint="__('At least 8 characters. Share it with the company admin.')" viewable required />
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="create-tenant-form" variant="primary">
                <span wire:loading.remove wire:target="save">{{ __('Create company') }}</span>
                <span wire:loading wire:target="save">{{ __('Creating…') }}</span>
            </flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
