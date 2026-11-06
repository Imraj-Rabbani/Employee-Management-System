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

                @if ($document)
                    <x-approval-timeline :document="$document" />
                @endif

                @if ($canAnswer)
                    <form wire:submit="approve" id="approval-respond-form" class="space-y-4">
                        <x-form.textarea wire:model="comment" :label="__('Comment')" :hint="__('Required to reject or send back.')" />

                        <x-form.file wire:model="attachment" :label="__('Attachment')" :required="$approval->step->attachment_required"
                            :hint="__('PDF, image, Word or Excel, max 5 MB')" />
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
