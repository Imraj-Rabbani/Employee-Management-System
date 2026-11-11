{{-- <x-approval-timeline :document="$leave" />  Class: Workflow\View\Components\ApprovalTimeline --}}
<div {{ $attributes->class('space-y-4') }}>
    @forelse ($approvals as $approval)
        <div class="flex gap-3" wire:key="approval-{{ $approval->id }}">
            <div class="flex flex-col items-center">
                <span @class([
                    'mt-1 size-2.5 rounded-full',
                    'bg-amber-500' => $approval->status->value === 'pending',
                    'bg-green-500' => $approval->status->value === 'approved',
                    'bg-red-500' => $approval->status->value === 'rejected',
                    'bg-sky-500' => $approval->status->value === 'review',
                ])></span>
                @unless ($loop->last)
                    <span class="mt-1 w-px flex-1 bg-zinc-200 dark:bg-white/10"></span>
                @endunless
            </div>

            <div class="min-w-0 flex-1 pb-2 text-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="text-zinc-500">{{ __('Level :level', ['level' => $approval->level]) }}</span>
                    <span class="font-medium">{{ $name($approval->approver_user_id) }}</span>
                    <x-ui.badge :color="$approval->status->color()">{{ $approval->status->label() }}</x-ui.badge>
                    @if ($approval->amount !== null)
                        <span class="text-zinc-600 dark:text-zinc-300">{{ rtrim(rtrim($approval->amount, '0'), '.') }}</span>
                    @endif
                </div>

                <div class="mt-0.5 text-xs text-zinc-400">
                    @if ($approval->responded_at)
                        {{ format_datetime($approval->responded_at) }}
                        @if ($approval->responded_by !== $approval->approver_user_id)
                            · {{ __('answered by :name', ['name' => $name($approval->responded_by)]) }}
                        @endif
                    @else
                        {{ __('Asked :when', ['when' => format_datetime($approval->created_at)]) }}
                    @endif
                </div>

                @if ($approval->comment)
                    <p class="mt-1 whitespace-pre-line rounded-lg bg-zinc-50 px-3 py-2 text-zinc-700 dark:bg-white/5 dark:text-zinc-300">{{ $approval->comment }}</p>
                @endif
            </div>
        </div>
    @empty
        <x-ui.empty icon="clock" :heading="__('Not submitted yet')" :description="__('The approval history will appear here.')" />
    @endforelse
</div>
