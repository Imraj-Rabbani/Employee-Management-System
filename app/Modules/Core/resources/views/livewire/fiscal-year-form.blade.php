<div>
    <x-ui.modal name="fiscal-year-form" :heading="$yearId ? __('Edit fiscal year') : __('New fiscal year')">
        <form wire:submit="save" id="fiscal-year-form" class="space-y-6">
            <x-form.input wire:model="name" :label="__('Name')" :hint="__('e.g. FY 2025-26')" required />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.date wire:model="startDate" :label="__('Starts')" required />
                <x-form.date wire:model="endDate" :label="__('Ends')" required />
            </div>
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="fiscal-year-form" variant="primary">{{ __('Save') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
