{{-- GET /workflow/email/{tenant}/{approval}?expires=…&signature=… (EmailApprovalController). No login. --}}
@php
    /** @var \App\Modules\Workflow\Models\Approval $approval */
    $isOpen = $approval->status === \App\Modules\Workflow\Enums\ApprovalStatus::Pending;
@endphp
<x-layouts.auth>
    <div class="flex flex-col gap-6">
        <x-auth-header :title="$typeName" :description="$subject" />

        <x-auth-session-status class="text-center" :status="session('status')" />

        <dl class="grid grid-cols-3 gap-y-2 text-sm">
            <dt class="text-zinc-500">{{ __('Requested by') }}</dt>
            <dd class="col-span-2">{{ $requester?->name ?? '-' }}</dd>
            <dt class="text-zinc-500">{{ __('Submitted') }}</dt>
            <dd class="col-span-2">{{ format_datetime($approval->created_at) }}</dd>
            <dt class="text-zinc-500">{{ __('Level') }}</dt>
            <dd class="col-span-2">{{ $approval->level }}</dd>
            <dt class="text-zinc-500">{{ __('Status') }}</dt>
            <dd class="col-span-2">
                <x-ui.badge :color="$approval->status->color()">{{ $approval->status->label() }}</x-ui.badge>
            </dd>
        </dl>

        @if ($document instanceof \App\Modules\Workflow\Contracts\ShowsApprovalDetails)
            @include($document->approvalDetailsView(), ['document' => $document])
        @endif

        @if ($isOpen)
            <form method="POST" action="{{ $actionUrl }}" class="flex flex-col gap-4">
                @csrf

                <x-form.textarea name="comment" :label="__('Comment')"
                    :placeholder="__('Required to reject or send back')" />

                @error('decision')
                    <p class="text-sm text-red-600 dark:text-red-400">{{ $message }}</p>
                @enderror

                <div class="flex flex-col gap-2">
                    <flux:button variant="primary" type="submit" name="decision" value="approved" class="w-full">{{ __('Approve') }}</flux:button>
                    @if ($workflow->allow_review)
                        <flux:button type="submit" name="decision" value="review" class="w-full">{{ __('Send back') }}</flux:button>
                    @endif
                    @if ($workflow->allow_reject)
                        <flux:button variant="danger" type="submit" name="decision" value="rejected" class="w-full">{{ __('Reject') }}</flux:button>
                    @endif
                </div>
            </form>
        @else
            <p class="text-center text-sm text-zinc-500">
                {{ __('This request has already been answered.') }}
                @if ($approval->responded_at)
                    ({{ format_datetime($approval->responded_at) }})
                @endif
            </p>
        @endif
    </div>
</x-layouts.auth>
