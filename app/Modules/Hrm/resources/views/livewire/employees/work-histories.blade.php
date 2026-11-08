<div>
    <x-ui.card :heading="__('Earlier jobs')" :subheading="__('Work experience before joining this company.')" :padding="false">
        @if ($canUpdate)
            <x-slot:actions>
                <flux:button size="sm" icon="plus" wire:click="add">{{ __('Add job') }}</flux:button>
            </x-slot:actions>
        @endif

        @if ($rows->isEmpty())
            <x-ui.empty icon="briefcase" :heading="__('No earlier jobs added yet')" />
        @else
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    <tr class="border-b border-white/50 dark:border-white/10">
                        <th class="px-6 py-3 font-medium">{{ __('Organization') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Designation') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('From') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('To') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Duration') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Letter') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-white/50 last:border-0 dark:border-white/10" wire:key="work-{{ $row->id }}">
                            <td class="px-6 py-3">
                                @if ($canUpdate)
                                    <button type="button" wire:click="edit({{ $row->id }})" class="text-left font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $row->organization }}</button>
                                @else
                                    <span class="font-medium">{{ $row->organization }}</span>
                                @endif
                                @if ($row->leaving_reason)
                                    <div class="text-xs text-zinc-500">{{ $row->leaving_reason }}</div>
                                @endif
                            </td>
                            <td class="px-6 py-3">{{ $row->designation ?? '—' }}</td>
                            <td class="px-6 py-3">{{ format_date($row->from_date) ?: '—' }}</td>
                            <td class="px-6 py-3">{{ format_date($row->to_date) ?: '—' }}</td>
                            <td class="px-6 py-3">{{ $row->duration() ?? '—' }}</td>
                            <td class="px-6 py-3">
                                @if ($row->attachment_path)
                                    <a href="{{ route('hrm.employees.attachment', [$employee, $section, $row->id]) }}" class="inline-flex items-center gap-1 text-brand-800 hover:underline dark:text-brand-200">
                                        <flux:icon.paper-clip variant="micro" /> {{ __('Download') }}
                                    </a>
                                @else
                                    —
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </x-ui.card>

    @if ($canUpdate)
        <x-form.lookup-modal :$modal :$record size="lg" :heading="$record ? __('Edit job') : __('Add job')"
            :delete-message="__('The row and its letter file are deleted.')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input wire:model="form.organization" :label="__('Organization')" required />
                <x-form.input wire:model="form.designation" :label="__('Designation')" />
                <x-form.date wire:model="form.from_date" :label="__('From')" />
                <x-form.date wire:model="form.to_date" :label="__('To')" />
            </div>
            <x-form.input wire:model="form.leaving_reason" :label="__('Reason for leaving')" />
            <x-form.attachment :$record :label="$record?->attachment_path ? __('Replace experience letter') : __('Experience letter')"
                :url="$record ? route('hrm.employees.attachment', [$employee, $section, $record->id]) : null" />
        </x-form.lookup-modal>
    @endif
</div>
