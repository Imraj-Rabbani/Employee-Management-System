<div>
    <x-ui.card :heading="__('Education')" :padding="false">
        <x-slot:actions>
            <flux:button size="sm" icon="plus" wire:click="add">{{ __('Add education') }}</flux:button>
        </x-slot:actions>

        @if ($rows->isEmpty())
            <x-ui.empty icon="academic-cap" :heading="__('No education added yet')" />
        @else
            <ul class="divide-y divide-white/50 dark:divide-white/10">
                @foreach ($rows as $row)
                    <li class="px-6 py-3 text-sm" wire:key="edu-{{ $row->id }}">
                        <button type="button" wire:click="edit({{ $row->id }})" class="text-left font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $row->degree }}</button>
                        <span class="text-zinc-500">{{ $row->subject ? '· '.$row->subject : '' }}</span>
                        <div class="text-xs text-zinc-500">
                            {{ collect([$row->institution, $row->result(), $row->passing_year])->filter()->join(' · ') ?: '—' }}
                        </div>
                    </li>
                @endforeach
            </ul>
        @endif
    </x-ui.card>

    <x-form.lookup-modal :$modal :$record size="lg" :heading="$record ? __('Edit education') : __('Add education')"
        :delete-message="__('This row is deleted.')">
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.input wire:model="form.degree" :label="__('Degree / exam')" required :hint="__('e.g. SSC, HSC, B Sc (Hon\'s), MBA')" />
            <x-form.input wire:model="form.subject" :label="__('Subject / group')" />
        </div>
        <x-form.input wire:model="form.institution" :label="__('Institution')" />
        <div class="grid gap-4 sm:grid-cols-3">
            <x-form.input wire:model="form.score" :label="__('Score')" type="number" step="0.01" min="0" :hint="__('GPA, CGPA or %')" />
            <x-form.input wire:model="form.out_of" :label="__('Out of')" type="number" step="0.01" min="0" :hint="__('e.g. 5, 4 or 100')" />
            <x-form.input wire:model="form.passing_year" :label="__('Passing year')" type="number" min="1950" :max="now()->year + 1" />
        </div>
    </x-form.lookup-modal>
</div>
