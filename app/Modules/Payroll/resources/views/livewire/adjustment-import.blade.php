{{-- Import payroll adjustments (AdjustmentImport): all rows or none; problems listed per row. --}}
<div>
    <x-ui.modal name="adjustment-import" :heading="__('Import adjustments')"
        :subheading="__('Excel or CSV, one adjustment per row. Every row is checked first: all are sent for approval, or none.')">
        <form wire:submit="import" id="adjustment-import" class="space-y-4">
            <flux:text>
                {{ __('Columns:') }} <code class="text-xs">{{ implode(', ', $columns) }}</code>.
                {{ __('Head = the salary head\'s code or name; amount always positive.') }}
                <flux:link :href="route('payroll.adjustments.template')">{{ __('Download the template') }}</flux:link>
            </flux:text>

            <flux:field>
                <flux:label>{{ __('File') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <input type="file" wire:model="file" accept=".xlsx,.xls,.csv" class="block w-full text-sm text-zinc-700 file:me-3 file:rounded-lg file:border-0 file:bg-brand-50 file:px-3 file:py-2 file:text-brand-800 dark:text-zinc-200 dark:file:bg-white/10 dark:file:text-white" />
                <flux:error name="file" />
            </flux:field>

            @if ($problems !== [])
                <div class="max-h-64 overflow-y-auto rounded-xl border border-red-200 bg-red-50/70 p-3 text-sm text-red-800 dark:border-red-400/30 dark:bg-red-500/10 dark:text-red-200">
                    <p class="mb-2 font-medium">{{ __('Nothing was imported. Fix these and upload again:') }}</p>
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
            <flux:button type="submit" form="adjustment-import" variant="primary" icon="arrow-up-tray" wire:loading.attr="disabled" wire:target="file,import">{{ __('Import') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
