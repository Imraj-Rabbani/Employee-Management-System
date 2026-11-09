<div class="space-y-4">
    {{-- Header --}}
    <x-ui.card>
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
    </x-ui.card>

    <div class="grid gap-4 lg:grid-cols-3">
        {{-- Personal details --}}
        <x-ui.card class="lg:col-span-2" :heading="__('Details')">
            <x-ui.details :items="[
                __('Gender') => $applicant->gender?->label(),
                __('Date of birth') => $applicant->birth_date ? format_date($applicant->birth_date) : null,
                __('National ID') => $applicant->national_id,
                __('Address') => $applicant->address,
                __('Expected salary') => $applicant->expected_salary !== null ? number_format((float) $applicant->expected_salary) : null,
                __('Added') => format_date($applicant->created_at),
            ]" />
            @if ($applicant->notes)
                <flux:text class="mt-4 whitespace-pre-line">{{ $applicant->notes }}</flux:text>
            @endif
        </x-ui.card>

        {{-- Applications --}}
        <x-ui.card :heading="__('Applications')" :padding="false">
            <x-slot:actions>
                <flux:button size="sm" icon="plus" wire:click="openApply" :disabled="! $postings">{{ __('Apply to posting') }}</flux:button>
            </x-slot:actions>

            @if ($applications->isEmpty())
                <x-ui.empty icon="briefcase" :heading="__('No applications yet')" />
            @else
                <ul class="divide-y divide-white/50 dark:divide-white/10">
                    @foreach ($applications as $application)
                        <li class="flex items-center justify-between gap-2 px-6 py-3 text-sm" wire:key="app-{{ $application->id }}">
                            <div class="min-w-0">
                                <a href="{{ route('hrm.recruitment.postings.show', $application->job_posting_id) }}" wire:navigate class="font-medium text-brand-800 hover:underline dark:text-brand-200">{{ $application->jobPosting->title }}</a>
                                <div class="text-xs text-zinc-500">{{ $application->jobPosting->number() }} · {{ format_date($application->applied_on) }}</div>
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
        </x-ui.card>
    </div>

    <x-ui.modal name="applicant-apply" :heading="__('Apply to posting')">
        <form wire:submit="apply" id="applicant-apply" class="space-y-4">
            <x-form.select wire:model="postingId" :label="__('Job posting')" :options="$postings" :placeholder="__('Choose…')" required
                :hint="__('Open postings that take applications today and this person has not applied to')" />
        </form>
        <x-slot:footer>
            <flux:button type="submit" form="applicant-apply" variant="primary">{{ __('Apply') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
