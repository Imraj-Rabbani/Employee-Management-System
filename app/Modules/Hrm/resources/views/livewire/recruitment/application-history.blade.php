<div>
    <x-ui.modal name="application-history"
        :heading="$application ? $application->applicant->name : __('History')"
        :subheading="$application ? $application->jobPosting->number().' · '.$application->jobPosting->title : null">
        @if ($application)
            {{-- Same look as the approval timeline (x-approval-timeline): a dot per move, a line between them. --}}
            <div class="space-y-4">
                @foreach ($application->stageChanges as $change)
                    <div class="flex gap-3" wire:key="change-{{ $change->id }}">
                        <div class="flex flex-col items-center">
                            <span @class([
                                'mt-1 size-2.5 rounded-full',
                                'bg-zinc-400' => $change->to_stage->color() === 'neutral',
                                'bg-sky-500' => $change->to_stage->color() === 'info',
                                'bg-green-500' => $change->to_stage->color() === 'success',
                                'bg-red-500' => $change->to_stage->color() === 'danger',
                                'bg-amber-500' => $change->to_stage->color() === 'warning',
                            ])></span>
                            @unless ($loop->last)
                                <span class="mt-1 w-px flex-1 bg-zinc-200 dark:bg-white/10"></span>
                            @endunless
                        </div>

                        <div class="min-w-0 flex-1 pb-2 text-sm">
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($change->from_stage)
                                    <span class="text-zinc-500">{{ $change->from_stage->label() }} →</span>
                                @endif
                                <x-ui.badge :color="$change->to_stage->color()">{{ $change->to_stage->label() }}</x-ui.badge>
                                @if ($change->rejectionReason)
                                    <span class="font-medium">{{ $change->rejectionReason->name }}</span>
                                @endif
                            </div>

                            <div class="mt-0.5 text-xs text-zinc-400">
                                {{ format_datetime($change->created_at) }}
                                · {{ $names[$change->changed_by_user_id] ?? __('System') }}
                            </div>

                            @if ($change->note)
                                <p class="mt-1 whitespace-pre-line rounded-lg bg-zinc-50 px-3 py-2 text-zinc-700 dark:bg-white/5 dark:text-zinc-300">{{ $change->note }}</p>
                            @endif
                        </div>
                    </div>
                @endforeach
            </div>
        @endif
    </x-ui.modal>
</div>
