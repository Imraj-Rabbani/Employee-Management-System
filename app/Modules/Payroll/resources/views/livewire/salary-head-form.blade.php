<x-form.lookup-modal :$modal :$record size="lg" :heading="$record ? __('Edit salary head') : __('New salary head')">
        <div class="grid gap-4 sm:grid-cols-3">
            <x-form.input wire:model="form.code" :label="__('Code')" required :hint="__('Letters, digits, _ — used in formulas')" />
            <x-form.input wire:model="form.name" :label="__('Name')" required class="sm:col-span-2" />
        </div>

        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.select wire:model.live="form.type" :label="__('Type')" :options="$types" required />
            @unless ($form['is_basic'] ?? false)
                <x-form.select wire:model.live="form.calc_method" :label="__('Calculation')" :options="$methods" required />
            @endunless
        </div>

        <x-form.toggle wire:model.live="form.is_basic" :label="__('This is the basic salary')" :hint="__('Its amount is set per employee; salary profiles work out the other heads from it.')" />
        @error('form.is_basic')
            <flux:text class="text-sm !text-red-600">{{ $message }}</flux:text>
        @enderror

        {{-- Only the field the chosen calculation needs (none for the basic head). --}}
        @php $method = ($form['is_basic'] ?? false) ? null : \App\Modules\Payroll\Enums\CalcMethod::tryFrom((string) ($form['calc_method'] ?? '')); @endphp
        @if ($method?->usesAmount())
            <x-form.input wire:model.live.debounce.400ms="form.amount" type="number" min="0" step="0.01" :label="$method === \App\Modules\Payroll\Enums\CalcMethod::Attendance ? __('Amount per day') : __('Amount')" required />
        @elseif ($method?->usesRate())
            <x-form.input wire:model.live.debounce.400ms="form.rate" type="number" min="0" step="0.0001" :label="__('Percent')" required />
        @elseif ($method === \App\Modules\Payroll\Enums\CalcMethod::Formula)
            <x-form.input wire:model.live.debounce.400ms="form.formula" :label="__('Formula')" required :hint="__('e.g. BASIC * 0.5. It is evaluated when payroll runs.')" />
        @endif

        {{-- Rule preview: the rule in words and what it comes to for a sample employee. --}}
        @if ($preview)
            <div class="space-y-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
                <flux:heading size="sm">{{ __('Rule preview') }}</flux:heading>
                <div class="grid gap-3 sm:grid-cols-3">
                    <x-form.input wire:model.live.debounce.400ms="sampleBasic" type="number" min="0" step="0.01" :label="__('Sample basic')" />
                    <x-form.input wire:model.live.debounce.400ms="sampleGross" type="number" min="0" step="0.01" :label="__('Sample gross')" />
                    <x-form.input wire:model.live.debounce.400ms="sampleDays" type="number" min="0" max="31" :label="__('Attendance days')" />
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
            <x-form.input wire:model.live.debounce.400ms="form.taxable_percent" type="number" min="0" max="100" step="0.01" :label="__('Taxable %')" required />
            <x-form.input wire:model.live.debounce.400ms="form.tax_exemption_cap" type="number" min="0" step="0.01" :label="__('Tax exemption cap')" :hint="__('Amount up to this is not taxed')" />
            <x-form.input wire:model="form.eligible_after_months" type="number" min="0" :label="__('Eligible after (months)')" :hint="__('Months of service needed')" required />
        </div>

        <div class="grid gap-4 sm:grid-cols-3">
            <x-form.select wire:model="form.pay_cycle" :label="__('Paid')" :options="$cycles" required />
            <x-form.input wire:model="form.sequence" type="number" min="0" :label="__('Order')" required :hint="__('Payslip and calculation order')" />
            <x-form.input wire:model="form.gl_account" :label="__('GL account')" />
        </div>

        <div class="grid gap-3 sm:grid-cols-2">
            <x-form.toggle wire:model="form.in_gross" :label="__('Counts in gross salary')" />
            <x-form.toggle wire:model="form.prorate" :label="__('Prorate for joiners and leavers')" />
            <x-form.toggle wire:model="form.arrear_eligible" :label="__('Arrears can be paid')" />
            <x-form.toggle wire:model="form.is_active" :label="__('Active')" />
        </div>
</x-form.lookup-modal>
