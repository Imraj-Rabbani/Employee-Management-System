{{-- Payroll settings (PayrollSettingsForm): head mapping for the payroll run, default pay scale profile. --}}
<form wire:submit="save" class="space-y-4">
    <div class="glass overflow-hidden rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ __('Heads for payroll jobs') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Which salary head the payroll run uses for each job. Leave empty when the company does not use it.') }}</flux:text>
            </div>
        </div>

        <div class="p-6">
            <div class="grid gap-x-6 gap-y-4 sm:grid-cols-2">
                @foreach ($roles as $role)
                    <flux:field>
                        <flux:label>{{ $role->label() }}</flux:label>
                        <flux:description>{{ $role->hint().' '.__('Type: :type.', ['type' => $role->headType()->label()]) }}</flux:description>
                        <flux:select wire:model="heads.{{ $role->value }}">
                            <flux:select.option value="">{{ __('Not used') }}</flux:select.option>
                            @foreach ($options[$role->value] as $optionValue => $optionLabel)
                                <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="heads.{{ $role->value }}" />
                    </flux:field>
                @endforeach
            </div>
        </div>
    </div>

    <div class="glass overflow-hidden rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ __('Pay scale') }}</flux:heading>
            </div>
        </div>

        <div class="p-6">
            <flux:field>
                <flux:label>{{ __('Default pay scale profile') }}</flux:label>
                <flux:description>{{ __('For employees without their own (set on the employee\'s Salary tab).') }}</flux:description>
                <flux:select wire:model="defaultPayScale">
                    <flux:select.option value="">{{ __('None') }}</flux:select.option>
                    @foreach ($payScales as $optionValue => $optionLabel)
                        <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="defaultPayScale" />
            </flux:field>
        </div>
    </div>

    <div class="glass overflow-hidden rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ __('Overtime rate') }}</flux:heading>
                <flux:text class="mt-1">{{ __('One overtime hour pays basic ÷ hours per month × the multiplier of the day type. Approved overtime hours only; paid into the Overtime head above.') }}</flux:text>
            </div>
        </div>

        <div class="grid gap-x-6 gap-y-4 p-6 sm:grid-cols-2 lg:grid-cols-5">
            <flux:field>
                <flux:label>{{ __('Hours per month') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:input aria-required="true" type="number" min="1" max="744" wire:model="otDivisor" />
                <flux:error name="otDivisor" />
            </flux:field>
            @foreach ($dayTypes as $dayType)
                <flux:field>
                    <flux:label>{{ __(':type × rate', ['type' => $dayType->label()]) }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" type="number" step="0.01" min="0" max="10" wire:model="otMultipliers.{{ $dayType->value }}" />
                    <flux:error name="otMultipliers.{{ $dayType->value }}" />
                </flux:field>
            @endforeach
        </div>
    </div>

    <div class="flex justify-end">
        <flux:button type="submit" variant="primary">{{ __('Save settings') }}</flux:button>
    </div>
</form>
