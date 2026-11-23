{{-- Uploaded amounts of an upload run. PHP side: App\Modules\Payroll\Livewire\PayrollRunAmounts --}}
<div class="glass overflow-hidden rounded-2xl">
    <div class="flex flex-wrap items-center justify-between gap-4 px-6 py-4">
        <div>
            <flux:heading size="lg">{{ __('Uploaded amounts') }}</flux:heading>
            <flux:text class="mt-1">
                @if ($count > 0)
                    {{ trans_choice(':count amount|:count amounts', $count) }} · {{ trans_choice(':count employee|:count employees', $people) }} · {{ __('total :total', ['total' => $total->format()]) }}
                @else
                    {{ __('Nothing uploaded yet. This run pays only the employees in its file.') }}
                @endif
            </flux:text>
        </div>
        @can('update', $run)
            <div class="flex flex-wrap gap-2">
                <flux:button size="sm" icon="arrow-down-tray" :href="route('payroll.runs.amounts-template', $run->id)">{{ __('Template') }}</flux:button>
                <flux:modal.trigger name="run-amounts-upload">
                    <flux:button size="sm" variant="primary" icon="arrow-up-tray">{{ $count > 0 ? __('Upload again') : __('Upload amounts') }}</flux:button>
                </flux:modal.trigger>
            </div>
        @endcan
    </div>

    <x-ui.modal name="run-amounts-upload" :heading="__('Upload amounts')"
        :subheading="__('Replaces all amounts of this run. Every row is checked first: all are taken, or none.')">
        <form wire:submit="upload" id="run-amounts-upload" class="space-y-4">
            <flux:text>
                {{ __('Columns:') }} <code class="text-xs">{{ implode(', ', $columns) }}</code>.
                @if ($run->head)
                    {{ __('Head may stay empty: this run pays :head.', ['head' => $run->head->name]) }}
                @else
                    {{ __('Head = the salary head\'s code or name; a deduction head takes money back.') }}
                @endif
            </flux:text>
            <flux:field>
                <flux:label>{{ __('File') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <input type="file" wire:model="file" accept=".xlsx,.xls,.csv" class="block w-full text-sm text-zinc-700 file:me-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-brand-800 dark:text-zinc-200 dark:file:bg-white/10 dark:file:text-white" />
                <flux:error name="file" />
            </flux:field>

            @if ($problems !== [])
                <div class="max-h-64 overflow-y-auto rounded-xl border border-red-200 bg-red-50/70 p-3 text-sm text-red-800 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-200">
                    <p class="mb-2 font-medium">{{ __('Nothing was uploaded. Fix these and try again:') }}</p>
                    <ul class="space-y-1">
                        @foreach ($problems as $problem)
                            <li>
                                @if ($problem['row'] > 0)
                                    <span class="font-medium">{{ __('Row :row', ['row' => $problem['row']]) }}{{ $problem['column'] !== '' ? ' · '.$problem['column'] : '' }}:</span>
                                @endif
                                {{ $problem['message'] }}
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endif
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="run-amounts-upload" variant="primary" icon="arrow-up-tray" wire:loading.attr="disabled" wire:target="file,upload">{{ __('Upload') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
