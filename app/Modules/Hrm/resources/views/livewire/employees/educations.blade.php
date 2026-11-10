<div>
    <x-ui.card :heading="__('Education')" :padding="false">
        @if ($canUpdate)
            <x-slot:actions>
                <flux:button size="sm" icon="plus" wire:click="add">{{ __('Add education') }}</flux:button>
            </x-slot:actions>
        @endif

        @if ($rows->isEmpty())
            <x-ui.empty icon="academic-cap" :heading="__('No education added yet')" />
        @else
            <table class="w-full text-sm">
                <thead class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                    <tr class="border-b border-white/50 dark:border-white/10">
                        <th class="px-6 py-3 font-medium">{{ __('Degree') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Institution') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Subject') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Result') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Year') }}</th>
                        <th class="px-6 py-3 font-medium">{{ __('Certificate') }}</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr class="border-b border-white/50 last:border-0 dark:border-white/10" wire:key="education-{{ $row->id }}">
                            <td class="px-6 py-3">
                                @if ($canUpdate)
                                    <button type="button" wire:click="edit({{ $row->id }})" class="text-left font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $row->degree }}</button>
                                @else
                                    <span class="font-medium">{{ $row->degree }}</span>
                                @endif
                            </td>
                            <td class="px-6 py-3">{{ $row->institution ?? '—' }}</td>
                            <td class="px-6 py-3">{{ $row->subject ?? '—' }}</td>
                            <td class="px-6 py-3 tabular-nums">{{ $row->result() ?? '—' }}</td>
                            <td class="px-6 py-3 tabular-nums">{{ $row->passing_year ?? '—' }}</td>
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
        <x-form.lookup-modal :$modal :$record size="lg" :heading="$record ? __('Edit education') : __('Add education')"
            :delete-message="__('The row and its certificate file are deleted.')">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input wire:model="form.degree" :label="__('Degree / exam')" required list="degree-suggestions" :hint="__('e.g. SSC, HSC, B Sc (Hon\'s), MBA')" />
                <datalist id="degree-suggestions">
                    @foreach ($degreeSuggestions as $degree)
                        <option value="{{ $degree }}"></option>
                    @endforeach
                </datalist>
                <x-form.input wire:model="form.subject" :label="__('Subject / group')" />
            </div>
            <x-form.input wire:model="form.institution" :label="__('Institution')" />
            <div class="grid gap-4 sm:grid-cols-3">
                <x-form.input wire:model="form.score" :label="__('Score')" type="number" step="0.01" min="0" :hint="__('GPA, CGPA or %')" />
                <x-form.input wire:model="form.out_of" :label="__('Out of')" type="number" step="0.01" min="0" :hint="__('e.g. 5, 4 or 100')" />
                <x-form.input wire:model="form.passing_year" :label="__('Passing year')" type="number" min="1950" :max="now()->year + 1" />
            </div>
            <x-form.attachment :$record :label="$record?->attachment_path ? __('Replace certificate') : __('Certificate')"
                :url="$record ? route('hrm.employees.attachment', [$employee, $section, $record->id]) : null" />
        </x-form.lookup-modal>
    @endif
</div>
