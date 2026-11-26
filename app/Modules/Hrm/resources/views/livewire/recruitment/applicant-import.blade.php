@php
    $statuses = [
        'new' => ['label' => __('New'), 'color' => 'success'],
        'existing' => ['label' => __('Already entered'), 'color' => 'info'],
        'skip' => ['label' => __('Skipped'), 'color' => 'neutral'],
        'error' => ['label' => __('Error'), 'color' => 'danger'],
    ];
    $usable = $counts['new'] + $counts['existing'];
@endphp
<div>
    <x-ui.modal name="applicant-import" size="xl"
        :heading="__('Import applicants')"
        :subheading="$posting ? __('Onto :no · :title', ['no' => $posting->number(), 'title' => $posting->title]) : __('From a Google Form\'s responses, or any spreadsheet with one person per line')">
        <div class="space-y-6">
            @unless ($postingFixed)
                <flux:field>
                    <flux:label>{{ __('Job posting') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:select wire:model.live="postingId" aria-required="true">
                        <flux:select.option value="">{{ $postings ? __('Choose…') : __('No posting is taking applications') }}</flux:select.option>
                        @foreach ($postings as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:description>{{ __('Everyone in the file applies to this posting. Only open postings inside their dates are listed.') }}</flux:description>
                    <flux:error name="postingId" />
                </flux:field>
            @endunless

            @if ($posting?->form_url || $posting?->responses_url)
                <div class="flex flex-wrap gap-x-5 gap-y-2 text-sm">
                    @if ($posting->responses_url)
                        <a href="{{ $posting->responses_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 font-medium text-brand-800 dark:text-brand-200 underline decoration-current/50 hover:decoration-current underline-offset-4 hover:text-blue-600 dark:hover:text-blue-400"><flux:icon.table-cells variant="micro" /> {{ __('Open the responses sheet') }}</a>
                    @endif
                    @if ($posting->form_url)
                        <a href="{{ $posting->form_url }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1 text-brand-800 dark:text-brand-200 underline decoration-current/50 hover:decoration-current underline-offset-4 hover:text-blue-600 dark:hover:text-blue-400"><flux:icon.clipboard-document-list variant="micro" /> {{ __('Application form') }}</a>
                    @endif
                </div>
            @endif

            @if ($problem)
                <flux:callout variant="warning" icon="exclamation-triangle" :heading="$problem" />
            @endif

            @if ($headers === [])
                {{-- Step 1: the file --}}
                <div class="space-y-3 text-sm text-zinc-600 dark:text-zinc-300">
                    <p>{{ __('Use the responses of a Google Form (or any spreadsheet with one person per line):') }}</p>
                    <ol class="list-decimal space-y-1 ps-5">
                        <li>{{ __('Open the form\'s responses sheet in Google Sheets.') }}</li>
                        <li>{{ __('File → Download → Comma-separated values (.csv) or Microsoft Excel (.xlsx).') }}</li>
                        <li>{{ __('Choose that file below. Nothing is saved until you press Import.') }}</li>
                    </ol>
                    <p>{{ __('The first line must hold the column names (the form\'s questions). Each person needs a name and an email or a phone.') }}</p>
                </div>

                {{-- The guide: which questions the Google Form should ask (their titles are what the import matches on). --}}
                <details class="group rounded-xl border border-white/50 dark:border-white/10">
                    <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-3 text-sm font-medium">
                        <span class="inline-flex items-center gap-2"><flux:icon.light-bulb variant="micro" class="text-amber-500" /> {{ __('Setting up the Google Form: which questions to ask') }}</span>
                        <flux:icon.chevron-down variant="micro" class="text-zinc-500 transition group-open:rotate-180" />
                    </summary>
                    <div class="space-y-3 border-t border-white/50 px-4 py-3 text-sm dark:border-white/10">
                        <p class="text-zinc-600 dark:text-zinc-300">{{ __('Use these question titles and every column is matched by itself. Other titles work too: you then choose the columns by hand.') }}</p>
                        <div class="overflow-x-auto">
                            <table class="w-full text-sm">
                                <thead class="text-left text-xs uppercase tracking-wide text-zinc-500 dark:text-zinc-400">
                                    <tr class="border-b border-white/50 dark:border-white/10">
                                        <th class="py-2 pe-4 font-medium">{{ __('Question title') }}</th>
                                        <th class="py-2 pe-4 font-medium">{{ __('Question type') }}</th>
                                        <th class="py-2 font-medium">{{ __('Needed') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($questions as $field => [$questionTitle, $questionType, $questionNeeded])
                                        <tr class="border-b border-white/50 last:border-0 dark:border-white/10" wire:key="question-{{ $field }}">
                                            <td class="py-2 pe-4 font-medium">{{ $questionTitle }}</td>
                                            <td class="py-2 pe-4 text-zinc-600 dark:text-zinc-300">{{ $questionType }}</td>
                                            <td class="py-2 text-zinc-600 dark:text-zinc-300">{{ $questionNeeded }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                        <ul class="list-disc space-y-1 ps-5 text-zinc-600 dark:text-zinc-300">
                            <li>{{ __('Mark "Full name" as required, and at least one of "Email" / "Phone number".') }}</li>
                            <li>{{ __('In the form\'s Responses tab, click "Link to Sheets" so the answers collect in a spreadsheet.') }}</li>
                            <li>{{ __('Keep the form and sheet links on the posting (Edit → Online application form) to find them again here.') }}</li>
                            <li>{{ __('A file-upload question makes applicants sign in to Google; their CV stays in your Google Drive and comes in as a link.') }}</li>
                        </ul>
                    </div>
                </details>

                <flux:field>
                    <flux:label>{{ __('File') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input type="file" wire:model="file" accept=".csv,.xlsx,.xls" aria-required="true" />
                    <flux:description>{{ __('.csv or .xlsx, up to 5 MB and :max lines', ['max' => \App\Modules\Hrm\Actions\ImportApplicants::MAX_ROWS]) }}</flux:description>
                    <flux:text wire:loading wire:target="file" class="mt-2 text-sm">{{ __('Reading…') }}</flux:text>
                    <flux:error name="file" />
                </flux:field>
            @else
                {{-- Step 2: which column is which, then the preview --}}
                <div class="flex flex-wrap items-center justify-between gap-2 text-sm">
                    <div class="flex items-center gap-2">
                        <flux:icon.document-text variant="micro" class="text-zinc-500" />
                        <span class="font-medium">{{ $fileName }}</span>
                        <span class="text-zinc-500">· {{ trans_choice(':count line|:count lines', count($rows)) }}</span>
                    </div>
                    <flux:button size="xs" variant="ghost" icon="arrow-path" wire:click="startOver">{{ __('Choose another file') }}</flux:button>
                </div>

                <div>
                    <flux:heading>{{ __('Columns') }}</flux:heading>
                    <flux:text class="mt-1">{{ __('Matched from the column names. Change any that are wrong; columns not chosen are not imported.') }}</flux:text>
                    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ($fields as $field => $label)
                            <flux:field>
                                <flux:label>
                                    {{ $label }}
                                    @if ($field === 'first_name')
                                        <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span>
                                    @endif
                                </flux:label>
                                <flux:select wire:model.live="mapping.{{ $field }}" :aria-required="$field === 'first_name' ? 'true' : null">
                                    <flux:select.option value="">{{ __('— not imported —') }}</flux:select.option>
                                    @foreach ($headers as $column => $header)
                                        <flux:select.option :value="$column">{{ $header }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="mapping.{{ $field }}" />
                            </flux:field>
                        @endforeach
                    </div>
                    <flux:text class="mt-3 text-xs">{{ __('Only a full name? Choose it as the name and leave the last name out. Google Form file uploads give a Drive link: choose it as the CV link.') }}</flux:text>
                </div>

                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:heading>{{ __('Preview') }}</flux:heading>
                        @foreach ($statuses as $status => $info)
                            @if ($counts[$status] > 0)
                                <x-ui.badge :color="$info['color']">{{ $counts[$status] }} · {{ $info['label'] }}</x-ui.badge>
                            @endif
                        @endforeach
                    </div>

                    @if ($results === [])
                        <flux:text class="mt-2">{{ __('Choose the column with the name to see the preview.') }}</flux:text>
                    @else
                        <div class="mt-3 max-h-96 overflow-auto rounded-xl border border-white/50 dark:border-white/10">
                            <table class="w-full text-sm">
                                <thead class="sticky top-0 bg-white/90 text-left text-xs uppercase tracking-wide text-zinc-500 backdrop-blur dark:bg-zinc-900/90 dark:text-zinc-400">
                                    <tr class="border-b border-white/50 dark:border-white/10">
                                        <th class="px-4 py-2 font-medium">{{ __('Line') }}</th>
                                        <th class="px-4 py-2 font-medium">{{ __('Name') }}</th>
                                        <th class="px-4 py-2 font-medium">{{ __('Email / phone') }}</th>
                                        <th class="px-4 py-2 font-medium">{{ __('Result') }}</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($results as $result)
                                        <tr class="border-b border-white/50 align-top last:border-0 dark:border-white/10" wire:key="import-line-{{ $result['line'] }}">
                                            <td class="px-4 py-2 tabular-nums text-zinc-500">{{ $result['line'] }}</td>
                                            <td class="px-4 py-2 font-medium">{{ $result['name'] ?: '—' }}</td>
                                            <td class="px-4 py-2">{{ $result['contact'] ?: '—' }}</td>
                                            <td class="px-4 py-2">
                                                <x-ui.badge :color="$statuses[$result['status']]['color']">{{ $statuses[$result['status']]['label'] }}</x-ui.badge>
                                                @if ($result['message'])
                                                    <div class="mt-1 text-xs text-zinc-600 dark:text-zinc-300">{{ $result['message'] }}</div>
                                                @endif
                                                @foreach ($result['warnings'] as $warning)
                                                    <div class="mt-1 text-xs text-amber-700 dark:text-amber-300">{{ $warning }}</div>
                                                @endforeach
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @endif
                </div>

                <flux:error name="import" />
            @endif
        </div>

        <x-slot:footer>
            @if ($headers !== [])
                <flux:button variant="primary" icon="arrow-down-tray" wire:click="import" :disabled="$usable === 0 || $problem !== null">
                    {{ trans_choice('Import :count person|Import :count people', $usable) }}
                </flux:button>
            @endif
        </x-slot:footer>
    </x-ui.modal>
</div>
