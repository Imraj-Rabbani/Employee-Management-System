<div class="space-y-4">
    {{-- Header --}}
    <div class="glass overflow-hidden rounded-2xl">
        <div class="p-6">
            <div class="flex flex-wrap items-center gap-5">
                <flux:avatar size="xl" :name="$applicant->name" />
                <div class="min-w-0 flex-1">
                    <flux:heading size="xl">{{ $applicant->name }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ collect([$applicant->current_designation, $applicant->current_employer])->filter()->join(' · ') ?: __('No current job given') }}
                    </flux:text>
                    <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-sm">
                        @if ($applicant->email)
                            <a href="mailto:{{ $applicant->email }}" class="inline-flex items-center gap-1 text-brand-800 hover:underline dark:text-brand-200"><flux:icon.envelope variant="micro" /> {{ $applicant->email }}</a>
                        @endif
                        @if ($applicant->phone)
                            <a href="tel:{{ $applicant->phone }}" class="inline-flex items-center gap-1 text-brand-800 hover:underline dark:text-brand-200"><flux:icon.phone variant="micro" /> {{ $applicant->phone }}</a>
                        @endif
                        @if ($applicant->cv_path)
                            <a href="{{ route('hrm.recruitment.applicants.cv', $applicant) }}" class="inline-flex items-center gap-1 text-brand-800 hover:underline dark:text-brand-200"><flux:icon.paper-clip variant="micro" /> {{ __('CV') }}</a>
                        @else
                            <span class="text-zinc-400">{{ __('No CV') }}</span>
                        @endif
                    </div>
                </div>
                <flux:button icon="pencil-square" x-on:click="$dispatch('edit-applicant', { id: {{ $applicant->id }} })">{{ __('Edit') }}</flux:button>
            </div>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Personal details --}}
        <div class="glass overflow-hidden rounded-2xl lg:col-span-2">
            <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
                <div>
                    <flux:heading size="lg">{{ __('Details') }}</flux:heading>
                </div>
            </div>

            <div class="p-6">
                <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ([
                            __('Gender') => $applicant->gender?->label(),
                            __('Date of birth') => $applicant->birth_date ? format_date($applicant->birth_date) : null,
                            __('National ID') => $applicant->national_id,
                            __('Address') => $applicant->address,
                            __('Expected salary') => $applicant->expected_salary !== null ? number_format((float) $applicant->expected_salary) : null,
                            __('Added') => format_date($applicant->created_at),
                    ] as $detailLabel => $detailValue)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $detailLabel }}</dt>
                            <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                @if ($detailValue === null || $detailValue === '')
                                    <span class="text-zinc-400">—</span>
                                @else
                                    {{ $detailValue }}
                                @endif
                            </dd>
                        </div>
                    @endforeach
                </dl>
                @if ($applicant->notes)
                    <flux:text class="mt-4 whitespace-pre-line">{{ $applicant->notes }}</flux:text>
                @endif
            </div>
        </div>

        {{-- Applications --}}
        <div class="glass overflow-hidden rounded-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
                <div>
                    <flux:heading size="lg">{{ __('Applications') }}</flux:heading>
                </div>
                <div class="flex shrink-0 items-center gap-2">
                    <flux:button size="sm" icon="plus" wire:click="openApply" :disabled="! $postings">{{ __('Apply to posting') }}</flux:button>
                </div>
            </div>

            @if ($applications->isEmpty())
                <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
                    <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                        <flux:icon name="briefcase" />
                    </div>
                    <flux:heading size="lg" class="mt-4">{{ __('No applications yet') }}</flux:heading>
                </div>
            @else
                <ul class="divide-y divide-white/50 dark:divide-white/10">
                    @foreach ($applications as $application)
                        <li class="flex items-center justify-between gap-2 px-6 py-3 text-sm" wire:key="app-{{ $application->id }}">
                            <div class="min-w-0">
                                <a href="{{ route('hrm.recruitment.postings.show', $application->job_posting_id) }}" wire:navigate class="font-medium text-brand-800 hover:underline dark:text-brand-200">{{ $application->jobPosting->title }}</a>
                                <div class="text-xs text-zinc-500">
                                    {{ $application->jobPosting->number() }} · {{ format_date($application->applied_on) }}
                                    @if ($application->hiredEmployee)
                                        · <a href="{{ route('hrm.employees.show', $application->hired_employee_id) }}" wire:navigate class="text-brand-800 hover:underline dark:text-brand-200">{{ __('Employee :code', ['code' => $application->hiredEmployee->code]) }}</a>
                                    @elseif ($offer = $application->offers->first())
                                        · <a href="{{ route('hrm.recruitment.offers.show', $offer->id) }}" wire:navigate class="text-brand-800 hover:underline dark:text-brand-200">{{ $offer->number() }} {{ mb_strtolower($offer->status()->label()) }}</a>
                                    @endif
                                </div>
                            </div>
                            {{-- The badge opens the application's history (ApplicationHistory). --}}
                            <button type="button" x-on:click="$dispatch('show-application-history', { id: {{ $application->id }} })" title="{{ __('History') }}">
                                <x-ui.badge :color="$application->stage->color()">{{ $application->stage->label() }}</x-ui.badge>
                            </button>
                        </li>
                    @endforeach
                </ul>
            @endif
            @if ($hiddenApplications > 0)
                <flux:text class="px-6 pb-3 text-xs">{{ trans_choice('+:count application in org units you cannot see|+:count applications in org units you cannot see', $hiddenApplications, ['count' => $hiddenApplications]) }}</flux:text>
            @endif
        </div>
    </div>

    <x-ui.modal name="applicant-apply" :heading="__('Apply to posting')">
        <form wire:submit="apply" id="applicant-apply" class="space-y-4">
            <flux:field>
                <flux:label>{{ __('Job posting') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:description>{{ __('Open postings that take applications today and this person has not applied to') }}</flux:description>
                <flux:select aria-required="true" wire:model="postingId">
                    <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                    @foreach ($postings as $optionValue => $optionLabel)
                        <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="postingId" />
            </flux:field>
        </form>
        <x-slot:footer>
            <flux:button type="submit" form="applicant-apply" variant="primary">{{ __('Apply') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
