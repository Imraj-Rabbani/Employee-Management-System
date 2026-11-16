{{--
    Profile → Salary (EmployeeSalaryTab): current salary with every head, hold flags, history of salaries.
    Modals: "employee-salary" (change salary, from a profile or by hand) and "salary-hold".
--}}
@php
    $kind = fn (\App\Modules\Payroll\Data\SalaryLine $l) => match (true) {
        $l->head->type === \App\Modules\Payroll\Enums\HeadType::Deduction => __('deduction'),
        $l->head->type === \App\Modules\Payroll\Enums\HeadType::Info => __('information'),
        ! $l->inGross() => __('not in gross'),
        default => null,
    };
@endphp

<div class="space-y-4">
    {{-- Hold banner: the payroll run reads these flags. --}}
    @if ($employee->salary_on_hold || $employee->bank_transfer_on_hold)
        <div class="flex items-start justify-between gap-4 rounded-2xl bg-amber-500/10 px-5 py-3 text-sm ring-1 ring-amber-500/30" data-test="hold">
            <div>
                <div class="font-medium text-amber-800 dark:text-amber-200">
                    {{ $employee->salary_on_hold ? __('Salary on hold') : __('Bank transfer on hold') }}
                    @if ($employee->salary_on_hold && $employee->bank_transfer_on_hold) · {{ __('Bank transfer on hold') }} @endif
                </div>
                <div class="text-zinc-600 dark:text-zinc-300">{{ $employee->salary_hold_reason }}</div>
            </div>
            @if ($canManage)
                <flux:button size="sm" variant="ghost" wire:click="openHold">{{ __('Change') }}</flux:button>
            @endif
        </div>
    @endif

    {{-- Pay scale profile: how payroll treats the employee; none = the company default. --}}
    <div class="glass flex items-center justify-between gap-4 rounded-2xl px-6 py-3 text-sm" data-test="pay-scale">
        <div>
            <span class="text-zinc-500">{{ __('Pay scale profile') }}:</span>
            @if ($employee->payScaleProfile)
                <span class="font-medium">{{ $employee->payScaleProfile->name }}</span>
                <span class="text-zinc-500">· {{ $employee->payScaleProfile->summary() }}</span>
            @elseif ($defaultPayScale)
                <span class="font-medium">{{ $defaultPayScale->name }}</span> <span class="text-zinc-500">({{ __('company default') }})</span>
            @else
                <span class="text-zinc-500">{{ __('None (no default in the payroll settings)') }}</span>
            @endif
        </div>
        @if ($canManage)
            <flux:button size="sm" variant="ghost" wire:click="openPayScale">{{ __('Change') }}</flux:button>
        @endif
    </div>

    <div class="glass overflow-hidden rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ __('Current salary') }}</flux:heading>
                @if ($current)
                    <flux:text class="mt-1">{{ __('Since :date', ['date' => format_date($current->from)]).($current->profile ? ' · '.__('Profile: :name', ['name' => $current->profile]) : '') }}</flux:text>
                @endif
            </div>
            <div class="flex shrink-0 items-center gap-2">
                @if ($canManage)
                    <div class="flex items-center gap-2">
                        @unless ($employee->salary_on_hold || $employee->bank_transfer_on_hold)
                            <flux:button size="sm" variant="ghost" icon="pause" wire:click="openHold">{{ __('Hold') }}</flux:button>
                        @endunless
                        <flux:button size="sm" icon="banknotes" wire:click="openSalary">{{ $history->isEmpty() ? __('Set salary') : __('Change salary') }}</flux:button>
                    </div>
                @endif
            </div>
        </div>

        @if ($upcoming)
            <div class="border-b border-white/50 px-6 py-3 text-sm dark:border-white/10">
                <x-ui.badge color="info">{{ __('Upcoming') }}</x-ui.badge>
                {{ __('From :date: gross :gross', ['date' => format_date($upcoming->from), 'gross' => $upcoming->salary->gross->format()]) }}
            </div>
        @endif

        @if ($current === null)
            <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                    <flux:icon name="banknotes" />
                </div>
                <flux:heading size="lg" class="mt-4">{{ __('No salary yet') }}</flux:heading>
                <flux:text class="mt-1 max-w-sm">{{ $upcoming ? __('The first salary starts later (see above).') : __('Set a salary from a salary profile, or head by head.') }}</flux:text>
            </div>
        @else
            <table class="w-full text-sm" data-test="current">
                <tbody>
                    @foreach ($current->salary->lines as $line)
                        <tr class="border-b border-white/50 dark:border-white/10">
                            <td class="px-6 py-2">{{ $line->head->name }}</td>
                            <td class="px-3 py-2 text-zinc-500">{{ collect([$line->basis, $kind($line)])->filter()->implode(' · ') }}</td>
                            <td class="px-6 py-2 text-right tabular-nums">{{ $line->amount->format() }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="font-medium">
                    <tr><td class="px-6 pt-3" colspan="2">{{ __('Gross') }}</td><td class="px-6 pt-3 text-right tabular-nums">{{ $current->salary->gross->format() }}</td></tr>
                    <tr><td class="px-6" colspan="2">{{ __('Deductions') }}</td><td class="px-6 text-right tabular-nums">{{ $current->salary->deductions->format() }}</td></tr>
                    <tr><td class="px-6 pb-3" colspan="2">{{ __('Net') }}</td><td class="px-6 pb-3 text-right tabular-nums">{{ $current->salary->net->format() }}</td></tr>
                </tfoot>
            </table>
        @endif
    </div>

    @if ($history->isNotEmpty())
        <div class="glass overflow-hidden rounded-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
                <div>
                    <flux:heading size="lg">{{ __('Salary history') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Click a row for its heads.') }}</flux:text>
                </div>
            </div>

            <table class="w-full text-sm" data-test="history">
                <thead class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    <tr class="border-b border-white/50 dark:border-white/10">
                        <th class="px-6 py-3 font-medium">{{ __('From') }}</th>
                        <th class="px-3 py-3 font-medium">{{ __('To') }}</th>
                        <th class="px-3 py-3 font-medium">{{ __('Profile') }}</th>
                        <th class="px-3 py-3 text-right font-medium">{{ __('Gross') }}</th>
                        <th class="px-3 py-3 text-right font-medium">{{ __('Change') }}</th>
                        <th class="px-6 py-3 text-right font-medium">{{ __('Net') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($history->values() as $i => $row)
                        @php
                            $key = $row->from->toDateString();
                            $previous = $history->values()->get($i + 1);
                            $diff = $previous ? $row->salary->gross->subtract($previous->salary->gross) : null;
                        @endphp
                        <tr wire:key="salary-{{ $key }}" wire:click="toggle('{{ $key }}')" class="cursor-pointer border-b border-white/50 hover:bg-white/40 dark:border-white/10 dark:hover:bg-white/5">
                            <td class="px-6 py-2">{{ format_date($row->from) }}</td>
                            <td class="px-3 py-2">{{ $row->to ? format_date($row->to) : __('Open') }}</td>
                            <td class="px-3 py-2">{{ $row->profile ?? '—' }}</td>
                            <td class="px-3 py-2 text-right tabular-nums">{{ $row->salary->gross->format() }}</td>
                            <td @class(['px-3 py-2 text-right tabular-nums', 'text-green-700 dark:text-green-300' => $diff?->isPositive(), 'text-red-600' => $diff?->isNegative()])>
                                {{ $diff === null ? '—' : ($diff->isPositive() ? '+' : '').$diff->format() }}
                            </td>
                            <td class="px-6 py-2 text-right tabular-nums">{{ $row->salary->net->format() }}</td>
                        </tr>
                        @if ($openFrom === $key)
                            <tr wire:key="salary-{{ $key }}-heads" class="border-b border-white/50 bg-white/30 dark:border-white/10 dark:bg-white/5">
                                <td colspan="6" class="px-6 py-2">
                                    <div class="grid gap-x-8 gap-y-1 sm:grid-cols-2">
                                        @foreach ($row->salary->lines as $line)
                                            <div class="flex justify-between gap-4">
                                                <span>{{ $line->head->name }} <span class="text-zinc-500">{{ $line->basis }}</span></span>
                                                <span class="tabular-nums">{{ $line->amount->format() }}</span>
                                            </div>
                                        @endforeach
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    @if ($canManage)
        <x-ui.modal name="employee-salary" size="lg" :heading="__('Salary of :name', ['name' => $employee->name])">
            <form wire:submit="saveSalary" id="employee-salary" class="space-y-5">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Effective from') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                        <flux:description>{{ __('The current salary ends the day before. Same day as the current one = a correction.') }}</flux:description>
                        <flux:input type="date" aria-required="true" wire:model="form.effective_from" />
                        <flux:error name="form.effective_from" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Salary profile') }}</flux:label>
                        <flux:select wire:model="form.salary_profile_id">
                            <flux:select.option value="">{{ __('None') }}</flux:select.option>
                            @foreach ($profiles as $optionValue => $optionLabel)
                                <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="form.salary_profile_id" />
                    </flux:field>
                </div>

                <div class="grid items-end gap-3 sm:grid-cols-3">
                    <flux:field>
                        <flux:label>{{ __('Start from') }}</flux:label>
                        <flux:select wire:model="form.basis">
                            @foreach ($bases as $optionValue => $optionLabel)
                                <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="form.basis" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Amount') }}</flux:label>
                        <flux:input wire:model="form.amount" type="number" min="0" step="0.01" />
                        <flux:error name="form.amount" />
                    </flux:field>
                    <flux:button wire:click="applyProfile" icon="calculator">{{ __('Apply profile') }}</flux:button>
                </div>

                <flux:separator :text="__('Heads')" />
                @error('lines')
                    <flux:text class="text-sm !text-red-600">{{ $message }}</flux:text>
                @enderror
                <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($heads as $head)
                        <div wire:key="line-{{ $head->id }}" class="flex items-center justify-between gap-4 py-2">
                            <div class="min-w-0 text-sm">
                                <span class="font-medium">{{ $head->name }}</span>
                                <span class="text-xs text-zinc-500">
                                    {{ $head->is_basic ? __('Basic') : $head->type->label() }}
                                    @isset($workedOut[$head->id])
                                        · {{ $workedOut[$head->id]['rate'] + 0 }}% {{ $workedOut[$head->id]['value_type'] === 'percent_gross' ? __('of gross') : __('of basic') }}
                                    @endisset
                                </span>
                            </div>
                            <div class="w-36 shrink-0">
                                <flux:input type="number" step="0.01" min="0" size="sm" wire:model.live.debounce.400ms="lines.{{ $head->id }}" :aria-label="$head->name" />
                            </div>
                        </div>
                        @error('lines.'.$head->id)
                            <flux:text class="text-sm !text-red-600">{{ $message }}</flux:text>
                        @enderror
                    @endforeach
                </div>

                <div class="grid grid-cols-3 gap-3 text-sm">
                    <div><div class="text-xs text-zinc-500">{{ __('Gross') }}</div><div class="font-medium tabular-nums" data-test="typed-gross">{{ $typed->gross->format() }}</div></div>
                    <div><div class="text-xs text-zinc-500">{{ __('Deductions') }}</div><div class="font-medium tabular-nums">{{ $typed->deductions->format() }}</div></div>
                    <div><div class="text-xs text-zinc-500">{{ __('Net') }}</div><div class="font-medium tabular-nums">{{ $typed->net->format() }}</div></div>
                </div>
            </form>

            <x-slot:footer>
                <flux:button type="submit" form="employee-salary" variant="primary">{{ __('Save') }}</flux:button>
            </x-slot:footer>
        </x-ui.modal>

        <x-ui.modal name="pay-scale" :heading="__('Pay scale profile')">
            <form wire:submit="savePayScale" id="pay-scale" class="space-y-5">
                <flux:field>
                    <flux:label>{{ __('Pay scale profile') }}</flux:label>
                    <flux:select wire:model="payScaleId">
                        <flux:select.option value="">{{ $defaultPayScale ? __('Company default (:name)', ['name' => $defaultPayScale->name]) : __('None') }}</flux:select.option>
                        @foreach ($payScales as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="payScaleId" />
                </flux:field>
            </form>

            <x-slot:footer>
                <flux:button type="submit" form="pay-scale" variant="primary">{{ __('Save') }}</flux:button>
            </x-slot:footer>
        </x-ui.modal>

        <x-ui.modal name="salary-hold" :heading="__('Salary hold')">
            <form wire:submit="saveHold" id="salary-hold" class="space-y-5">
                <flux:switch wire:model.live="hold.salary_on_hold" :label="__('Hold the salary')" :description="__('Payroll leaves it unpaid until switched off.')" />

                <flux:switch wire:model.live="hold.bank_transfer_on_hold" :label="__('Hold the bank transfer')" :description="__('The salary is worked out, but not sent to the bank.')" />
                @if ($hold['salary_on_hold'] || $hold['bank_transfer_on_hold'])
                    <flux:field>
                        <flux:label>{{ __('Reason') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                        <flux:input aria-required="true" wire:model="hold.salary_hold_reason" />
                        <flux:error name="hold.salary_hold_reason" />
                    </flux:field>
                @endif
            </form>

            <x-slot:footer>
                <flux:button type="submit" form="salary-hold" variant="primary">{{ __('Save') }}</flux:button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
