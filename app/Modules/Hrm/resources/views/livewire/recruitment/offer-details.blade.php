@php use App\Modules\Hrm\Enums\OfferStatus; @endphp
<div class="space-y-4">
    <x-ui.card>
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
    </x-ui.card>

    <div class="grid gap-4 lg:grid-cols-3">
        <x-ui.card class="lg:col-span-2" :heading="__('Offer')">
            @include('hrm::approvals.job-offer', ['document' => $offer])
        </x-ui.card>
        <x-ui.card :heading="__('Approval')">
            @if ($offer->approvals->isNotEmpty())
                <x-approval-timeline :document="$offer" />
            @else
                <flux:text>{{ __('Not sent yet. Check the offer, then press "Send for approval".') }}</flux:text>
            @endif
        </x-ui.card>
    </div>

    {{-- Record the applicant's answer --}}
    <x-ui.modal name="offer-answer" :heading="__('The applicant\'s answer')">
        <form wire:submit="saveAnswer" id="offer-answer" class="space-y-4">
            <x-form.select wire:model.live="answer" :label="__('Answer')" :placeholder="__('Choose…')" required
                :options="[\App\Modules\Hrm\Models\JobOffer::ACCEPTED => __('Accepted'), \App\Modules\Hrm\Models\JobOffer::DECLINED => __('Declined')]" />
            <x-form.date wire:model="answeredOn" :label="__('Date')" required />
            @if ($answer === \App\Modules\Hrm\Models\JobOffer::DECLINED)
                <x-form.select wire:model="reasonId" :label="__('Reason')" :options="$reasons" :placeholder="__('Choose…')" required
                    :hint="__('The application is withdrawn with this reason.')" />
                <x-form.textarea wire:model="note" :label="__('Note')" rows="2" />
            @endif
        </form>
        <x-slot:footer>
            <flux:button type="submit" form="offer-answer" variant="primary">{{ __('Save') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
