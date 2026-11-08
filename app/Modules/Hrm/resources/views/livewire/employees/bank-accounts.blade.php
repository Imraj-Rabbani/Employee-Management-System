<div>
    <x-ui.card :heading="__('Bank accounts')"
        :subheading="__('Salary goes to the primary account. Payment method (Job tab): :method.', ['method' => $paymentMethod ?? __('not set')])" :padding="false">
        @if ($canUpdate)
            <x-slot:actions>
                <flux:button size="sm" icon="plus" wire:click="add">{{ __('Add account') }}</flux:button>
            </x-slot:actions>
        @endif

        @if ($rows->isEmpty())
            <x-ui.empty icon="building-library" :heading="__('No bank accounts yet')" />
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
                                    <button type="button" wire:click="edit({{ $row->id }})" class="text-left font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $row->bank_name }}</button>
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
    </x-ui.card>

    @if ($canUpdate)
        <x-form.lookup-modal :$modal :$record :heading="$record ? __('Edit bank account') : __('Add bank account')"
            :delete-message="__('If this is the primary account, the next account becomes primary.')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input wire:model="form.bank_name" :label="__('Bank')" required />
                <x-form.input wire:model="form.branch_name" :label="__('Branch')" />
                <x-form.input wire:model="form.account_no" :label="__('Account number')" required />
                <x-form.input wire:model="form.routing_no" :label="__('Routing number')" inputmode="numeric" :hint="__('9 digits')" />
            </div>
            <x-form.input wire:model="form.account_name" :label="__('Account name')" :hint="__('As printed on the cheque book, if different from the employee\'s name.')" />
            <x-form.toggle wire:model="form.is_primary" :label="__('Primary account')"
                :hint="$rows->isEmpty() ? __('The first account is always primary.') : __('Salary goes here. The other accounts stop being primary.')" />
        </x-form.lookup-modal>
    @endif
</div>
