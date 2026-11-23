{{-- New / changed payroll adjustment (AdjustmentForm). Sent for approval on save. --}}
<div>
    <x-ui.modal name="adjustment-form" :heading="$adjustmentId ? __('Change the adjustment and send it again') : __('New adjustment')"
        :subheading="__('A one-time earning or deduction, paid by the regular payroll run of that month once approved.')">
        <form wire:submit="save" id="adjustment-form" class="space-y-4">
            <flux:field>
                <flux:label>{{ __('Employee') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:select aria-required="true" wire:model="employeeId" :disabled="$adjustmentId !== null">
                    <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                    @foreach ($employees as $optionValue => $optionLabel)
                        <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="employeeId" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Head') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:select aria-required="true" wire:model.live="headId">
                    <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                    @foreach ($heads as $optionValue => $optionLabel)
                        <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="headId" />
            </flux:field>

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('Year') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" type="number" min="2000" max="2100" wire:model="year" />
                    <flux:error name="year" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Month') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:select aria-required="true" wire:model="month">
                        @foreach (range(1, 12) as $m)
                            <flux:select.option value="{{ $m }}">{{ \Carbon\Carbon::create(2000, $m)->translatedFormat('F') }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="month" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ $isDeduction ? __('Amount deducted') : __('Amount') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" inputmode="decimal" wire:model="amount" />
                    <flux:error name="amount" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Reason') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:textarea aria-required="true" wire:model="reason" rows="2" />
                <flux:error name="reason" />
            </flux:field>
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="adjustment-form" variant="primary" icon="paper-airplane">{{ __('Send for approval') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
