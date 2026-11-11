<div class="flex flex-wrap gap-2">
    <flux:button size="sm" icon="link" wire:click="linkUnmatched">{{ __('Match unmatched punches') }}</flux:button>
    <flux:modal.trigger name="punch-upload">
        <flux:button size="sm" variant="primary" icon="arrow-up-tray">{{ __('Upload punches') }}</flux:button>
    </flux:modal.trigger>

    <x-ui.modal name="punch-upload" :heading="__('Upload punches')">
        <form wire:submit="upload" id="punch-upload-form" class="space-y-4">
            <flux:text class="text-sm">
                {{ __('A CSV or Excel file. First row: the column names access_code and punched_at (or access_code, date and time). One punch per row, e.g. 1001, 2026-12-01 08:55. Punches already stored are skipped.') }}
            </flux:text>
            <flux:link :href="route('payroll.attendance.punches.template')" class="text-sm">{{ __('Download an example file') }}</flux:link>
            <x-form.file wire:model="file" :label="__('File')" accept=".csv,.txt,.xlsx,.xls" />

            @if ($problems)
                <flux:callout variant="danger" icon="exclamation-triangle" :heading="__('Nothing was saved. Fix these and upload again:')">
                    <flux:callout.text>
                        <ul class="list-disc space-y-0.5 ps-4">
                            @foreach (array_slice($problems, 0, 20) as $problem)
                                <li>{{ $problem }}</li>
                            @endforeach
                        </ul>
                        @if (count($problems) > 20)
                            <p class="mt-1">{{ __('… and :count more.', ['count' => count($problems) - 20]) }}</p>
                        @endif
                    </flux:callout.text>
                </flux:callout>
            @endif
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="punch-upload-form" variant="primary" icon="arrow-up-tray">{{ __('Upload') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
