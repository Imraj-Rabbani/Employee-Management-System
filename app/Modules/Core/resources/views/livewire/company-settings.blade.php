<div class="max-w-2xl space-y-6">
    {{-- Plan & usage (read-only): the limits come from App\Core\Enums\Plan, the counts from PlanLimits. --}}
    @php
        $usage = [
            ['label' => __('Employees'), 'used' => $employeesUsed, 'max' => $plan->maxEmployees(),
             'text' => __(':used of :max', ['used' => $employeesUsed, 'max' => $plan->maxEmployees()])],
            ['label' => __('Storage'), 'used' => $storageUsed, 'max' => $plan->storageBytes(),
             'text' => __(':used of :max GB', ['used' => \App\Core\Support\PlanLimits::formatBytes($storageUsed), 'max' => $plan->storageGb()])],
        ];
    @endphp
    <div class="glass overflow-hidden rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ __('Plan & usage') }}</flux:heading>
                <flux:text class="mt-1">{{ __('What your plan includes and how much of it you use.') }}</flux:text>
            </div>
            <x-ui.badge :color="$plan === \App\Core\Enums\Plan::Business ? 'brand' : 'neutral'">{{ __(':plan plan', ['plan' => $plan->label()]) }}</x-ui.badge>
        </div>

        <div class="space-y-5 p-6">
            @foreach ($usage as $row)
                @php $percent = $row['max'] > 0 ? min(100, round($row['used'] / $row['max'] * 100)) : 100; @endphp
                <div>
                    <div class="mb-1.5 flex justify-between text-sm">
                        <span class="font-medium text-zinc-800 dark:text-white">{{ $row['label'] }}</span>
                        <span class="text-zinc-600 dark:text-zinc-300">{{ $row['text'] }}</span>
                    </div>
                    <div class="h-2 overflow-hidden rounded-full bg-zinc-200 dark:bg-white/10" role="progressbar" aria-label="{{ $row['label'] }}" aria-valuenow="{{ $percent }}" aria-valuemin="0" aria-valuemax="100">
                        <div @class(['h-full rounded-full', 'bg-red-600' => $percent >= 100, 'bg-amber-500' => $percent >= 80 && $percent < 100, 'bg-brand-700' => $percent < 80]) style="width: {{ $percent }}%"></div>
                    </div>
                </div>
            @endforeach

            @if ($plan === \App\Core\Enums\Plan::Free)
                <flux:text size="sm">{{ __('Need more? The Business plan allows :employees employees and :gb GB of storage. Contact us to upgrade.', ['employees' => \App\Core\Enums\Plan::Business->maxEmployees(), 'gb' => \App\Core\Enums\Plan::Business->storageGb()]) }}</flux:text>
            @endif
        </div>
    </div>

    <div class="glass overflow-hidden rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ $companyName }}</flux:heading>
                <flux:text class="mt-1">{{ __('General settings for your company.') }}</flux:text>
            </div>
        </div>

        <div class="p-6">
            <form wire:submit="save" id="company-settings-form" class="space-y-6">
                <flux:field>
                    <flux:label>{{ __('Timezone') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('Dates and times in the app are shown in this timezone.') }}</flux:description>
                    <flux:select aria-required="true" wire:model="timezone">
                        @foreach ($timezones as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="timezone" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Date format') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('How dates are shown everywhere in the app.') }}</flux:description>
                    <flux:select aria-required="true" wire:model="dateFormat">
                        @foreach ($dateFormats as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="dateFormat" />
                </flux:field>

                <div class="grid gap-4 sm:grid-cols-2 items-start">
                    <flux:field>
                        <flux:label>{{ __('Fiscal year') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                        <flux:input aria-required="true" wire:model="fiscalYear" :placeholder="__('e.g. 2025-2026')" />
                        <flux:description>{{ __('Shown in the top bar exactly as typed.') }}</flux:description>
                        <flux:error name="fiscalYear" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Currency') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                        <flux:input aria-required="true" wire:model="currency" :placeholder="__('e.g. BDT')" />
                        <flux:description>{{ __('Shown next to amounts exactly as typed.') }}</flux:description>
                        <flux:error name="currency" />
                    </flux:field>
                </div>
            </form>
        </div>

        <div class="border-t border-white/50 px-6 py-4 text-sm text-zinc-600 dark:border-white/10 dark:text-zinc-300">
            <div class="flex justify-end">
                <flux:button type="submit" form="company-settings-form" variant="primary">
                    <span wire:loading.remove wire:target="save">{{ __('Save') }}</span>
                    <span wire:loading wire:target="save">{{ __('Saving…') }}</span>
                </flux:button>
            </div>
        </div>
    </div>
</div>
