{{-- Payroll settings (PayrollSettingsForm): head mapping for the payroll run, default pay scale profile. --}}
<form wire:submit="save" class="space-y-4">
    <x-ui.card :heading="__('Heads for payroll jobs')" :subheading="__('Which salary head the payroll run uses for each job. Leave empty when the company does not use it.')">
        <div class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
            @foreach ($roles as $role)
                <x-form.select wire:model="heads.{{ $role->value }}" :label="$role->label()" :options="$options[$role->value]"
                    :placeholder="__('Not used')" :hint="$role->hint().' '.__('Type: :type.', ['type' => $role->headType()->label()])" />
            @endforeach
        </div>
    </x-ui.card>

    <x-ui.card :heading="__('Pay scale')">
        <x-form.select wire:model="defaultPayScale" :label="__('Default pay scale profile')" :options="$payScales" :placeholder="__('None')"
            :hint="__('For employees without their own (set on the employee\'s Salary tab).')" />
    </x-ui.card>

    <div class="flex justify-end">
        <flux:button type="submit" variant="primary">{{ __('Save settings') }}</flux:button>
    </div>
</form>
