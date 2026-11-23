{{-- New / edit payroll run. PHP side: App\Modules\Payroll\Livewire\PayrollRunForm --}}
<div>
    <x-ui.modal name="payroll-run-form" :heading="$run ? __('Edit run :code', ['code' => $run->code]) : __('New payroll run')">
        <form wire:submit="save" id="payroll-run-form" class="space-y-6">
            @if ($run)
                {{-- Period, type and scope are fixed once the run is made. --}}
                <dl class="grid grid-cols-2 gap-x-4 gap-y-2 text-sm">
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Period') }}</dt><dd>{{ $run->period()->label() }}</dd>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Type') }}</dt><dd>{{ $run->run_type->label() }}{{ $run->head ? ' · '.$run->head->name : '' }}</dd>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Scope') }}</dt><dd>{{ $run->entity->name ?? __('Whole company') }}</dd>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Employees') }}</dt><dd>{{ $run->employee_count }}</dd>
                    <dt class="text-zinc-500 dark:text-zinc-400">{{ __('Status') }}</dt><dd><x-ui.badge :color="$run->status->color()">{{ $run->status->label() }}</x-ui.badge></dd>
                </dl>
                <flux:separator />
            @else
                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:field>
                        <flux:label>{{ __('Year') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                        <flux:input type="number" min="2000" max="2100" aria-required="true" wire:model="form.year" />
                        <flux:error name="form.year" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Month') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                        <flux:select aria-required="true" wire:model="form.month">
                            @foreach (range(1, 12) as $m)
                                <flux:select.option :value="$m">{{ \Carbon\Carbon::create(2000, $m, 1)->translatedFormat('F') }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="form.month" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Cycle') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                        <flux:select aria-required="true" wire:model="form.cycle">
                            @foreach (\App\Core\Support\PeriodCycle::options() as $optionValue => $optionLabel)
                                <flux:select.option :value="$optionValue">{{ __($optionLabel) }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="form.cycle" />
                    </flux:field>
                </div>
                <flux:error name="form.period" />

                <div class="grid items-start gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Type') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                        <flux:select aria-required="true" wire:model.live="form.run_type">
                            @foreach ($types as $optionValue => $optionLabel)
                                <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="form.run_type" />
                    </flux:field>
                    @if (($form['run_type'] ?? '') === 'head_wise')
                        <flux:field>
                            <flux:label>{{ __('Head') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                            <flux:select aria-required="true" wire:model="form.salary_head_id">
                                <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                                @foreach ($heads as $optionValue => $optionLabel)
                                    <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="form.salary_head_id" />
                        </flux:field>
                    @endif
                </div>

                @php
                    $headWise = ($form['run_type'] ?? '') === 'head_wise';
                    $upload = ($form['run_type'] ?? '') === 'off_cycle' || ($headWise && ($form['amount_basis'] ?? '') === 'upload');
                @endphp
                @if ($headWise)
                    <flux:field>
                        <flux:label>{{ __('Amounts from') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                        <flux:select aria-required="true" wire:model.live="form.amount_basis">
                            @foreach ($bases as $basis)
                                <flux:select.option :value="$basis->value">{{ $basis->label() }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:description>{{ \App\Modules\Payroll\Enums\AmountBasis::tryFrom($form['amount_basis'] ?? '')?->hint() }}</flux:description>
                        <flux:error name="form.amount_basis" />
                    </flux:field>

                    @unless ($upload)
                        {{-- Who is paid (legacy scheduled disbursements): empty = everyone in the scope. --}}
                        <div class="space-y-4 rounded-xl border border-zinc-200/70 p-4 dark:border-white/10">
                            <flux:checkbox.group wire:model="form.religions" :label="__('Religions')" :description="__('None ticked = everyone.')" class="flex flex-wrap gap-x-6">
                                @foreach ($religionOptions as $optionValue => $optionLabel)
                                    <flux:checkbox :value="$optionValue" :label="$optionLabel" />
                                @endforeach
                            </flux:checkbox.group>
                            <div class="grid gap-4 sm:grid-cols-2">
                                <flux:field>
                                    <flux:label>{{ __('Joined before') }}</flux:label>
                                    <flux:input type="date" wire:model="form.joined_before" />
                                    <flux:error name="form.joined_before" />
                                </flux:field>
                                <div class="space-y-3 pt-1">
                                    <flux:switch wire:model="form.confirmed_only" :label="__('Confirmed employees only')" />
                                    <flux:switch wire:model="form.prorate_by_service" :label="__('Prorate by service this year')" />
                                </div>
                            </div>
                        </div>
                    @endunless
                @elseif (($form['run_type'] ?? '') === 'off_cycle')
                    <flux:text class="text-sm">{{ __('An off-cycle run pays uploaded amounts: upload the file on the run\'s page after saving.') }}</flux:text>
                @endif

                <flux:field>
                    <flux:label>{{ __('Scope') }}@unless ($wholeCompany) <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span>@endunless</flux:label>
                    <flux:select wire:model="form.entity_id" :aria-required="$wholeCompany ? 'false' : 'true'">
                        @if ($wholeCompany)
                            <flux:select.option value="">{{ __('Whole company') }}</flux:select.option>
                        @endif
                        @foreach ($units as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.entity_id" />
                </flux:field>

                @unless ($upload)
                    <flux:field>
                        <flux:label>{{ __('Only these employees') }}</flux:label>
                        <flux:textarea wire:model="form.employee_codes" rows="2" :placeholder="__('e.g. E001, E002 (empty = everyone in the scope)')" />
                        <flux:error name="form.employee_codes" />
                    </flux:field>
                @endunless
            @endif

            <flux:field>
                <flux:label>{{ __('Pay date') }}</flux:label>
                <flux:input type="date" wire:model="form.pay_date" />
                <flux:error name="form.pay_date" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Note') }}</flux:label>
                <flux:textarea wire:model="form.note" rows="2" />
                <flux:error name="form.note" />
            </flux:field>

            @if ($run?->status->isEditable())
                <flux:separator />
                <flux:button size="sm" variant="ghost" icon="trash" class="!text-red-600" x-on:click="$dispatch('confirm', {
                    title: @js(__('Delete this run?')),
                    message: @js(__('Its employees and calculated amounts are deleted too.')),
                    confirmText: @js(__('Delete')),
                    onConfirm: () => $wire.delete(),
                })">{{ __('Delete') }}</flux:button>
            @endif
        </form>

        <x-slot:footer>
            @if (! $run || $run->status->isEditable())
                <flux:button type="submit" form="payroll-run-form" variant="primary">{{ $run ? __('Save') : __('Create run') }}</flux:button>
            @endif
        </x-slot:footer>
    </x-ui.modal>
</div>
