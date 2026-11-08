<div>
    <x-ui.card :heading="__('Family members')" :padding="false">
        @if ($canUpdate)
            <x-slot:actions>
                <flux:button size="sm" icon="plus" wire:click="add">{{ __('Add family member') }}</flux:button>
            </x-slot:actions>
        @endif

        @if ($rows->isEmpty())
            <x-ui.empty icon="users" :heading="__('No family members yet')" />
        @else
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    <tr class="border-b border-white/50 dark:border-white/10">
                        <th class="px-6 py-3 font-medium">{{ __('Name') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Relation') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Date of birth') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Gender') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Occupation') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Phone') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-white/50 last:border-0 dark:border-white/10" wire:key="family-{{ $row->id }}">
                            <td class="px-6 py-3">
                                @if ($canUpdate)
                                    <button type="button" wire:click="edit({{ $row->id }})" class="text-left font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $row->name }}</button>
                                @else
                                    <span class="font-medium">{{ $row->name }}</span>
                                @endif
                                @if ($row->name_local)
                                    <div class="text-xs text-zinc-500">{{ $row->name_local }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-3">{{ $row->relation?->label() ?? '—' }}</td>
                            <td class="px-6 py-3">
                                {{ format_date($row->birth_date) ?: '—' }}
                                @if ($row->birth_date)
                                    <span class="text-xs text-zinc-500">({{ trans_choice(':count year|:count years', (int) $row->birth_date->diffInYears(today())) }})</span>
                                @endif
                            </td>
                            <td class="px-6 py-3">{{ $row->gender?->label() ?? '—' }}</td>
                            <td class="px-6 py-3">{{ $row->occupation ?? '—' }}</td>
                            <td class="px-6 py-3">{{ $row->phone ?? '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    @if ($canUpdate)
        <x-form.lookup-modal :$modal :$record size="lg" :heading="$record ? __('Edit family member') : __('Add family member')"
            :delete-message="__('The family member is removed from this employee.')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.select wire:model="form.relation" :label="__('Relation')" :options="$relations" :placeholder="__('Choose…')" required />
                <x-form.input wire:model="form.name" :label="__('Name')" required />
                <x-form.input wire:model="form.name_local" :label="__('Name (local)')" />
                <x-form.date wire:model="form.birth_date" :label="__('Date of birth')" />
                <x-form.select wire:model="form.gender" :label="__('Gender')" :options="$genders" :placeholder="__('Not set')" />
                <x-form.input wire:model="form.occupation" :label="__('Occupation')" />
                <x-form.input wire:model="form.phone" :label="__('Phone')" type="tel" />
            </div>
        </x-form.lookup-modal>
    @endif
</div>
