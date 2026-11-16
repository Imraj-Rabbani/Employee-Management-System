@php use App\Modules\Hrm\Enums\OfferStatus; @endphp
<div class="space-y-4">
    <div class="glass overflow-hidden rounded-2xl">
        <div class="p-6">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:heading size="xl">{{ $offer->number() }}</flux:heading>
                        <x-ui.badge :color="$status->color()">{{ $status->label() }}</x-ui.badge>
                    </div>
                    <flux:text class="mt-1">
                        <a href="{{ route('hrm.recruitment.applicants.show', $offer->application->applicant_id) }}" wire:navigate class="font-medium text-brand-800 hover:underline dark:text-brand-200">{{ $offer->application->applicant->name }}</a>
                        · <a href="{{ route('hrm.recruitment.postings.show', $offer->application->job_posting_id) }}" wire:navigate class="hover:underline">{{ $offer->application->jobPosting->title }}</a>
                    </flux:text>
                    <flux:text class="mt-1 text-xs">
                        @if ($offer->letter_sent_at) {{ __('Letter emailed :when', ['when' => format_datetime($offer->letter_sent_at)]) }} @endif
                        @if ($offer->responded_on) · {{ $status->label() }} {{ format_date($offer->responded_on) }} @endif
                    </flux:text>
                </div>

                <div class="flex flex-wrap gap-2">
                    @if ($offer->isEditable())
                        <flux:button icon="pencil-square" x-on:click="$dispatch('edit-offer', { id: {{ $offer->id }} })">{{ __('Edit') }}</flux:button>
                        <flux:button variant="primary" icon="paper-airplane" x-on:click="$dispatch('confirm', {
                            title: @js(__('Send for approval?')),
                            message: @js(__('You cannot change the offer while it is waiting for approval.')),
                            confirmText: @js(__('Send')),
                            onConfirm: () => $wire.submit(),
                        })">{{ $status === OfferStatus::SentBack ? __('Send again') : __('Send for approval') }}</flux:button>
                    @endif

                    @if (in_array($status, [OfferStatus::Approved, OfferStatus::LetterSent, OfferStatus::Accepted, OfferStatus::Declined], true))
                        <flux:button icon="arrow-down-tray" :href="route('hrm.recruitment.offers.letter', $offer->id)">{{ __('Letter (PDF)') }}</flux:button>
                    @endif

                    @if ($status === OfferStatus::Accepted)
                        @if ($offer->application->hiredEmployee)
                            <flux:button variant="primary" icon="user" :href="route('hrm.employees.show', $offer->application->hired_employee_id)" wire:navigate>
                                {{ __('Employee :code', ['code' => $offer->application->hiredEmployee->code]) }}
                            </flux:button>
                        @elseif (auth()->user()->can('create', \App\Modules\Hrm\Models\Employee::class))
                            <flux:button variant="primary" icon="user-plus" x-on:click="$dispatch('hire', { offerId: {{ $offer->id }} })">{{ __('Hire') }}</flux:button>
                        @endif
                    @endif

                    @if (in_array($status, [OfferStatus::Approved, OfferStatus::LetterSent], true))
                        <flux:button icon="envelope" x-on:click="$dispatch('confirm', {
                            title: @js(__('Email the offer letter?')),
                            message: @js(__('To :email, with the letter as a PDF.', ['email' => $offer->application->applicant->email ?? '—'])),
                            confirmText: @js(__('Send email')),
                            onConfirm: () => $wire.emailLetter(),
                        })">{{ $status === OfferStatus::LetterSent ? __('Email again') : __('Email letter') }}</flux:button>
                        <flux:button variant="primary" icon="check-badge" wire:click="openAnswer">{{ __('Record answer') }}</flux:button>
                    @endif
                </div>
            </div>
        </div>
    </div>

    <div class="grid gap-4 lg:grid-cols-3">
        <div class="glass overflow-hidden rounded-2xl lg:col-span-2">
            <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
                <div>
                    <flux:heading size="lg">{{ __('Offer') }}</flux:heading>
                </div>
            </div>

            <div class="p-6">
                @include('hrm::approvals.job-offer', ['document' => $offer])
            </div>
        </div>
        <div class="glass overflow-hidden rounded-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
                <div>
                    <flux:heading size="lg">{{ __('Approval') }}</flux:heading>
                </div>
            </div>

            <div class="p-6">
                @if ($offer->approvals->isNotEmpty())
                    <x-approval-timeline :document="$offer" />
                @else
                    <flux:text>{{ __('Not sent yet. Check the offer, then press "Send for approval".') }}</flux:text>
                @endif
            </div>
        </div>
    </div>

    {{-- Record the applicant's answer --}}
    <x-ui.modal name="offer-answer" :heading="__('The applicant\'s answer')">
        <form wire:submit="saveAnswer" id="offer-answer" class="space-y-4">
            <flux:field>
                <flux:label>{{ __('Answer') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:select aria-required="true" wire:model.live="answer">
                    <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                    @foreach ([\App\Modules\Hrm\Models\JobOffer::ACCEPTED => __('Accepted'), \App\Modules\Hrm\Models\JobOffer::DECLINED => __('Declined')] as $optionValue => $optionLabel)
                        <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:error name="answer" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Date') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                <flux:input type="date" aria-required="true" wire:model="answeredOn" />
                <flux:error name="answeredOn" />
            </flux:field>
            @if ($answer === \App\Modules\Hrm\Models\JobOffer::DECLINED)
                <flux:field>
                    <flux:label>{{ __('Reason') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('The application is withdrawn with this reason.') }}</flux:description>
                    <flux:select aria-required="true" wire:model="reasonId">
                        <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                        @foreach ($reasons as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="reasonId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Note') }}</flux:label>
                    <flux:textarea wire:model="note" rows="2" />
                    <flux:error name="note" />
                </flux:field>
            @endif
        </form>
        <x-slot:footer>
            <flux:button type="submit" form="offer-answer" variant="primary">{{ __('Save') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
