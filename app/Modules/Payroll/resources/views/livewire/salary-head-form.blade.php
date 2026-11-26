<div>
    <x-ui.modal :name="$modal" size="lg" :heading="$record ? __('Edit salary head') : __('New salary head')">
        <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('Code') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('Letters, digits, _ — used in formulas') }}</flux:description>
                    <flux:input aria-required="true" wire:model="form.code" />
                    <flux:error name="form.code" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" wire:model="form.name" class="sm:col-span-2" />
                    <flux:error name="form.name" />
                </flux:field>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Type') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:select aria-required="true" wire:model.live="form.type">
                        @foreach ($types as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.type" />
                </flux:field>
                @unless ($form['is_basic'] ?? false)
                    <flux:field>
                        <flux:label>{{ __('Calculation') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                        <flux:select aria-required="true" wire:model.live="form.calc_method">
                            @foreach ($methods as $optionValue => $optionLabel)
                                <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="form.calc_method" />
                    </flux:field>
                @endunless
            </div>

            <flux:switch wire:model.live="form.is_basic" :label="__('This is the basic salary')" :description="__('Its amount is set per employee; salary profiles work out the other heads from it.')" />
            @error('form.is_basic')
                <flux:text class="text-sm !text-red-600">{{ $message }}</flux:text>
            @enderror

            {{-- Only the field the chosen calculation needs (none for the basic head). --}}
            @php $method = ($form['is_basic'] ?? false) ? null : \App\Modules\Payroll\Enums\CalcMethod::tryFrom((string) ($form['calc_method'] ?? '')); @endphp
            @if ($method?->usesAmount())
                <flux:field>
                    <flux:label>{{ $method === \App\Modules\Payroll\Enums\CalcMethod::Attendance ? __('Amount per day') : __('Amount') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" wire:model.live.debounce.400ms="form.amount" type="number" min="0" step="0.01" />
                    <flux:error name="form.amount" />
                </flux:field>
            @elseif ($method?->usesRate())
                <flux:field>
                    <flux:label>{{ __('Percent') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" wire:model.live.debounce.400ms="form.rate" type="number" min="0" step="0.0001" />
                    <flux:error name="form.rate" />
                </flux:field>
            @elseif ($method === \App\Modules\Payroll\Enums\CalcMethod::Formula)
                <flux:field>
                    <flux:label>{{ __('Formula') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input :placeholder="__('e.g. BASIC * 0.5')" aria-required="true" wire:model.live.debounce.400ms="form.formula" />
                    <flux:description>{{ __('It is evaluated when payroll runs.') }}</flux:description>
                    <flux:error name="form.formula" />
                </flux:field>
            @endif

            {{-- Rule preview: the rule in words and what it comes to for a sample employee. --}}
            @if ($preview)
                <div class="space-y-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                    <flux:heading size="sm">{{ __('Rule preview') }}</flux:heading>
                    <div class="grid gap-3 sm:grid-cols-3">
                        <flux:field>
                            <flux:label>{{ __('Sample basic') }}</flux:label>
                            <flux:input wire:model.live.debounce.400ms="sampleBasic" type="number" min="0" step="0.01" />
                            <flux:error name="sampleBasic" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Sample gross') }}</flux:label>
                            <flux:input wire:model.live.debounce.400ms="sampleGross" type="number" min="0" step="0.01" />
                            <flux:error name="sampleGross" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Attendance days') }}</flux:label>
                            <flux:input wire:model.live.debounce.400ms="sampleDays" type="number" min="0" max="31" />
                            <flux:error name="sampleDays" />
                        </flux:field>
                    </div>
                    <flux:text>
                        {{ $preview['rule'] }}
                        @if ($preview['formula'])
                            — {{ __('worked out by the payroll run, so no preview here.') }}
                        @else
                            = <strong data-test="preview-amount">{{ $preview['amount'] }}</strong>
                            <span class="text-zinc-500">({{ __('taxed: :amount', ['amount' => $preview['taxable']]) }})</span>
                        @endif
                    </flux:text>
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('Taxable %') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" wire:model.live.debounce.400ms="form.taxable_percent" type="number" min="0" max="100" step="0.01" />
                    <flux:error name="form.taxable_percent" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Tax exemption cap') }}</flux:label>
                    <flux:description>{{ __('Amount up to this is not taxed') }}</flux:description>
                    <flux:input wire:model.live.debounce.400ms="form.tax_exemption_cap" type="number" min="0" step="0.01" />
                    <flux:error name="form.tax_exemption_cap" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Eligible after (months)') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('Months of service needed') }}</flux:description>
                    <flux:input aria-required="true" wire:model="form.eligible_after_months" type="number" min="0" />
                    <flux:error name="form.eligible_after_months" />
                </flux:field>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('Paid') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:select aria-required="true" wire:model="form.pay_cycle">
                        @foreach ($cycles as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.pay_cycle" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Order') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('Payslip and calculation order') }}</flux:description>
                    <flux:input aria-required="true" wire:model="form.sequence" type="number" min="0" />
                    <flux:error name="form.sequence" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('GL account') }}</flux:label>
                    <flux:input wire:model="form.gl_account" />
                    <flux:error name="form.gl_account" />
                </flux:field>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <flux:switch wire:model="form.in_gross" :label="__('Counts in gross salary')" />

                <flux:switch wire:model="form.prorate" :label="__('Prorate for joiners and leavers')" />
                <flux:switch wire:model="form.arrear_eligible" :label="__('Arrears can be paid')" />

                <flux:switch wire:model="form.is_active" :label="__('Active')" />
            </div>

            {{-- Delete: only when editing an existing record --}}
            @if ($record)
                <flux:separator />
                <flux:button size="sm" variant="ghost" icon="trash" class="!text-red-600" x-on:click="$dispatch('confirm', {
                    title: {{ Js::from(__('Delete this record?')) }},
                    message: {{ Js::from(__('Only possible when nothing uses it any more.')) }},
                    confirmText: {{ Js::from(__('Delete')) }},
                    onConfirm: () => $wire.delete(),
                })">{{ __('Delete') }}</flux:button>
            @endif
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="{{ $modal }}" variant="primary">{{ __('Save') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
