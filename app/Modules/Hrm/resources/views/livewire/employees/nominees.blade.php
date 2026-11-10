<div>
    <x-ui.card :heading="__('Nominees')" :subheading="__('Who receives the employee\'s benefits. The shares must add up to 100%.')" :padding="false">
        <x-slot:actions>
            @if ($rows->isNotEmpty())
                <x-ui.badge :color="$complete ? 'success' : 'warning'">{{ __('Total :total%', ['total' => $total]) }}</x-ui.badge>
            @endif
            @if ($canUpdate)
                <flux:button size="sm" icon="plus" wire:click="add">{{ __('Add nominee') }}</flux:button>
            @endif
        </x-slot:actions>

        @if ($rows->isEmpty())
            <x-ui.empty icon="user-group" :heading="__('No nominees yet')" />
        @else
            @unless ($complete)
                <flux:callout variant="warning" icon="exclamation-triangle" class="m-4"
                    :heading="__('The shares add up to :total%, not 100%.', ['total' => $total])" />
            @endunless

            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    <tr class="border-b border-white/50 dark:border-white/10">
                        <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Relation') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('National ID') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Phone') }}</th>
                        <th class="px-6 py-3 text-right font-medium">{{ __('Share') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-white/50 last:border-0 dark:border-white/10" wire:key="nominee-{{ $row->id }}">
                            <td class="px-6 py-3">
                                @if ($canUpdate)
                                    <button type="button" wire:click="edit({{ $row->id }})" class="text-left font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $row->name }}</button>
                                @else
                                    <span class="font-medium">{{ $row->name }}</span>
                                @endif
                                @if ($row->is_minor)
                                    <div class="text-xs text-zinc-500">{{ __('Minor · guardian: :name', ['name' => $row->guardian['name'] ?? '—']) }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-3">{{ $row->relation?->label() ?? '—' }}</td>
                            <td class="px-6 py-3">{{ $row->national_id ?? '—' }}</td>
                            <td class="px-6 py-3">{{ $row->phone ?? '—' }}</td>
                            <td class="px-6 py-3 text-right tabular-nums">{{ rtrim(rtrim($row->share_percent, '0'), '.') }}%</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    @if ($canUpdate)
        <x-form.lookup-modal :$modal :$record size="lg" :heading="$record ? __('Edit nominee') : __('Add nominee')"
            :delete-message="__('The nominee is removed. Remember to share their percentage among the others.')">
            @if ($family && ! $record)
                <x-form.select :label="__('Copy from family member')" :options="$family" :placeholder="__('Choose to fill name and relation…')"
                    x-on:change="$event.target.value && $wire.copyFamily($event.target.value)" />
            @endif

            <div class="grid gap-4 sm:grid-cols-[2fr_1fr_1fr]">
                <x-form.input wire:model="form.name" :label="__('Name')" required />
                <x-form.select wire:model="form.relation" :label="__('Relation')" :options="$relations" :placeholder="__('Not set')" />
                <x-form.input wire:model="form.share_percent" :label="__('Share %')" type="number" step="0.01" min="0.01" max="100" required />
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input wire:model="form.national_id" :label="__('National ID')" />
                <x-form.input wire:model="form.phone" :label="__('Phone')" type="tel" />
            </div>
            <x-form.textarea wire:model="form.address" :label="__('Address')" rows="2" />

            <x-form.toggle wire:model.live="form.is_minor" :label="__('Minor (under 18)')" :hint="__('A minor needs a guardian.')" />
            @if ($form['is_minor'] ?? false)
                <div class="grid gap-4 rounded-xl bg-zinc-500/5 p-4 sm:grid-cols-2">
                    <x-form.input wire:model="form.guardian_name" :label="__('Guardian name')" required />
                    <x-form.input wire:model="form.guardian_relation" :label="__('Guardian relation')" :hint="__('to the nominee, e.g. Mother')" />
                    <x-form.input wire:model="form.guardian_national_id" :label="__('Guardian national ID')" />
                    <x-form.input wire:model="form.guardian_phone" :label="__('Guardian phone')" type="tel" />
                </div>
            @endif
        </x-form.lookup-modal>
    @endif
</div>
