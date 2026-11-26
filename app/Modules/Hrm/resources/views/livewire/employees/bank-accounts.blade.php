<div>
    <div class="glass overflow-hidden rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ __('Bank accounts') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Salary goes to the primary account. Payment method (Job tab): :method.', ['method' => $paymentMethod ?? __('not set')]) }}</flux:text>
            </div>
            @if ($canUpdate)
                <div class="flex shrink-0 items-center gap-2">
                    <flux:button size="sm" icon="plus" wire:click="add">{{ __('Add account') }}</flux:button>
                </div>
            @endif
        </div>

        @if ($rows->isEmpty())
            <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
                <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                    <flux:icon name="building-library" />
                </div>
                <flux:heading size="lg" class="mt-4">{{ __('No bank accounts yet') }}</flux:heading>
            </div>
        @else
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    <tr class="border-b border-white/50 dark:border-white/10">
                        <th class="px-6 py-3 font-medium">{{ __('Bank') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Branch') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Routing no.') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Account name') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Account no.') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-white/50 last:border-0 dark:border-white/10" wire:key="bank-{{ $row->id }}">
                            <td class="px-6 py-3">
                                @if ($canUpdate)
                                    <button type="button" wire:click="edit({{ $row->id }})" class="text-left font-medium text-brand-800 underline-offset-4 dark:text-brand-200 underline decoration-current/50 hover:decoration-current hover:text-blue-600 dark:hover:text-blue-400">{{ $row->bank_name }}</button>
                                @else
                                    <span class="font-medium">{{ $row->bank_name }}</span>
                                @endif
                                @if ($row->is_primary)
                                    <x-ui.badge color="brand" class="ms-1">{{ __('Primary') }}</x-ui.badge>
                                @endif
                            </td>
                            <td class="px-6 py-3">{{ $row->branch_name ?? '—' }}</td>
                            <td class="px-6 py-3 tabular-nums">{{ $row->routing_no ?? '—' }}</td>
                            <td class="px-6 py-3">{{ $row->account_name ?? '—' }}</td>
                            <td class="px-6 py-3 tabular-nums">{{ $row->account_no }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    @if ($canUpdate)
        <div>
            <x-ui.modal :name="$modal" :heading="$record ? __('Edit bank account') : __('Add bank account')">
                <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
                    <div class="grid gap-4 sm:grid-cols-2">
                        <flux:field>
                            <flux:label>{{ __('Bank') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                            <flux:input aria-required="true" wire:model="form.bank_name" />
                            <flux:error name="form.bank_name" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Branch') }}</flux:label>
                            <flux:input wire:model="form.branch_name" />
                            <flux:error name="form.branch_name" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Account number') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                            <flux:input aria-required="true" wire:model="form.account_no" />
                            <flux:error name="form.account_no" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Routing number') }}</flux:label>
                            <flux:description>{{ __('9 digits') }}</flux:description>
                            <flux:input wire:model="form.routing_no" inputmode="numeric" />
                            <flux:error name="form.routing_no" />
                        </flux:field>
                    </div>
                    <flux:field>
                        <flux:label>{{ __('Account name') }}</flux:label>
                        <flux:description>{{ __('As printed on the cheque book, if different from the employee\'s name.') }}</flux:description>
                        <flux:input wire:model="form.account_name" />
                        <flux:error name="form.account_name" />
                    </flux:field>

                    <flux:switch wire:model="form.is_primary" :label="__('Primary account')" :description="$rows->isEmpty() ? __('The first account is always primary.') : __('Salary goes here. The other accounts stop being primary.')" />

                    {{-- Delete: only when editing an existing record --}}
                    @if ($record)
                        <flux:separator />
                        <flux:button size="sm" variant="ghost" icon="trash" class="!text-red-600" x-on:click="$dispatch('confirm', {
                            title: {{ Js::from(__('Delete this record?')) }},
                            message: {{ Js::from(__('If this is the primary account, the next account becomes primary.')) }},
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
    @endif
</div>
