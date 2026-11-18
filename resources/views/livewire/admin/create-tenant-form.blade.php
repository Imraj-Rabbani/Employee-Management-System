<div>
    <x-ui.modal name="create-tenant" :heading="__('New company')" :subheading="__('Creates the company, its own database, and its first admin login.')">
        <form wire:submit="save" class="space-y-6" id="create-tenant-form">
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('Code') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input :placeholder="__('e.g. sss')" aria-required="true" wire:model.blur="code" />
                    <flux:description>{{ __('Cannot be changed later.') }}</flux:description>
                    <flux:error name="code" />
                </flux:field>
                <div class="sm:col-span-2">
                    <flux:field>
                        <flux:label>{{ __('Company name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                        <flux:input aria-required="true" wire:model="name" />
                        <flux:error name="name" />
                    </flux:field>
                </div>
            </div>

            <flux:checkbox.group wire:model="modules" :label="__('Modules')" :description="__('Core and Self Service are always on.')">
                @foreach ($moduleOptions as $key => $label)
                    <flux:checkbox :value="$key" :label="$label" />
                @endforeach
            </flux:checkbox.group>

            <flux:separator :text="__('First admin login')" />

            <flux:field>
                <flux:label>{{ __('Name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:input aria-required="true" wire:model="adminName" />
                <flux:error name="adminName" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Email') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:input aria-required="true" wire:model="adminEmail" type="email" />
                <flux:error name="adminEmail" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Password') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:description>{{ __('At least 8 characters. Share it with the company admin.') }}</flux:description>
                <flux:input aria-required="true" wire:model="adminPassword" type="password" viewable />
                <flux:error name="adminPassword" />
            </flux:field>
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="create-tenant-form" variant="primary">
                <span wire:loading.remove wire:target="save">{{ __('Create company') }}</span>
                <span wire:loading wire:target="save">{{ __('Creating…') }}</span>
            </flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
