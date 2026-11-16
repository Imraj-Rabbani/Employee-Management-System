<div>
    <x-ui.modal name="approval-respond" size="lg" :heading="$approval?->step->workflow->approvalType->name ?? __('Approval')">
        @if ($approval)
            <div class="space-y-6">
                <div>
                    <flux:heading>{{ $document?->approvalSubject() ?? __('(deleted)') }}</flux:heading>
                    <flux:text class="mt-1">
                        {{ __('Requested by :name', ['name' => $requester?->name ?? '-']) }}
                        · {{ __('Level :level', ['level' => $approval->level]) }}
                    </flux:text>
                </div>

                @if ($document instanceof \App\Modules\Workflow\Contracts\ShowsApprovalDetails)
                    @include($document->approvalDetailsView(), ['document' => $document])
                @endif

                @if ($document)
                    <x-approval-timeline :document="$document" />
                @endif

                @if ($canAnswer)
                    <form wire:submit="approve" id="approval-respond-form" class="space-y-4">
                        @if ($document instanceof \App\Modules\Workflow\Contracts\AsksApproverForAmount)
                            <flux:field>
                                <flux:label>{{ $document->approverAmountLabel() }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                                <flux:description>{{ __('Needed to approve. You may change it.') }}</flux:description>
                                <flux:input aria-required="true" wire:model="amount" type="number" step="0.25" min="0" max="{{ $document->approverAmountMax() }}" class="w-36" />
                                <flux:error name="amount" />
                            </flux:field>
                        @endif
                        <flux:field>
                            <flux:label>{{ __('Comment') }}</flux:label>
                            <flux:description>{{ __('Required to reject or send back.') }}</flux:description>
                            <flux:textarea rows="3" wire:model="comment" />
                            <flux:error name="comment" />
                        </flux:field>

                        <flux:field>
                            <flux:label>{{ __('Attachment') }} @if ($approval->step->attachment_required)<span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span>@endif</flux:label>
                            <flux:description>{{ __('PDF, image, Word or Excel, max 5 MB') }}</flux:description>
                            <flux:input type="file" :aria-required="($approval->step->attachment_required) ? 'true' : null" wire:model="attachment" />
                            <flux:text wire:loading wire:target="attachment" class="mt-2 text-sm">{{ __('Uploading…') }}</flux:text>
                            <flux:error name="attachment" />
                        </flux:field>
                    </form>
                @endif
            </div>
        @endif

        @if ($canAnswer)
            <x-slot:footer>
                @if ($approval->step->workflow->allow_reject)
                    <flux:button variant="danger" wire:click="reject">{{ __('Reject') }}</flux:button>
                @endif
                @if ($approval->step->workflow->allow_review)
                    <flux:button wire:click="sendBack">{{ __('Send back') }}</flux:button>
                @endif
                <flux:button type="submit" form="approval-respond-form" variant="primary">{{ __('Approve') }}</flux:button>
            </x-slot:footer>
        @endif
    </x-ui.modal>
</div>
