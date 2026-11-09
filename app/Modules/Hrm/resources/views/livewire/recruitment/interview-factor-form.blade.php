<x-form.lookup-modal :$modal :$record :heading="$record ? __('Edit interview factor') : __('New interview factor')">
        <x-form.input wire:model="form.name" :label="__('Name')" required />
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.input wire:model="form.weight" type="number" min="0" max="100" step="0.01" :label="__('Weight (%)')" required
                :hint="__('Other active factors: :used%', ['used' => $otherWeight])" />
            <x-form.input wire:model="form.sequence" type="number" min="0" :label="__('Order')" required :hint="__('Order on the score sheet')" />
        </div>
        <x-form.textarea wire:model="form.description" :label="__('Description')" :hint="__('A hint for interviewers: what to look for')" />
        <x-form.toggle wire:model="form.is_required" :label="__('Required')" :hint="__('Interviewers must score this factor')" />
        <x-form.toggle wire:model="form.is_active" :label="__('Active')" />
</x-form.lookup-modal>
