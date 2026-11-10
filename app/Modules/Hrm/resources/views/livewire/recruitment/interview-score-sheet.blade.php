<div>
    <x-ui.modal name="interview-score-sheet" size="lg"
        :heading="$panelist ? __('Score sheet: :name', ['name' => $panelist->interview->application->applicant->name]) : __('Score sheet')"
        :subheading="$panelist ? $panelist->interview->title().' · '.$panelist->interview->application->jobPosting->title.' · '.__('Interviewer: :name', ['name' => $panelist->employee?->name]) : null">
        @if ($panelist)
            <form wire:submit="send" id="interview-score-sheet" class="space-y-5">
                @if ($onBehalf && $editable)
                    <flux:text class="rounded-lg bg-amber-500/10 px-3 py-2 text-sm text-amber-800 dark:text-amber-200">
                        {{ __('You are entering this sheet on behalf of :name.', ['name' => $panelist->employee?->name]) }}
                    </flux:text>
                @endif
                @unless ($editable)
                    <flux:text class="rounded-lg bg-zinc-500/10 px-3 py-2 text-sm">
                        {{ $panelist->isSubmitted() ? __('Sent :when. Result: :result%', ['when' => format_datetime($panelist->submitted_at), 'result' => $result ?? '—']) : __('The interview is :status: this sheet can no longer be changed.', ['status' => mb_strtolower($panelist->interview->status->label())]) }}
                    </flux:text>
                @endunless

                @if ($factors->isEmpty())
                    <x-ui.empty icon="clipboard-document-list" :heading="__('No interview factors')" :description="__('Add them in Recruitment → Setup → Interview factors.')" />
                @endif

                {{-- One row per factor: 1 … max as buttons (a radio group), and an optional note. --}}
                @foreach ($factors as $factor)
                    <div class="space-y-2 rounded-xl p-3 ring-1 ring-zinc-500/15" wire:key="factor-{{ $factor->id }}">
                        <div class="flex flex-wrap items-baseline justify-between gap-2">
                            <div>
                                <span class="font-medium">{{ $factor->name }}</span>
                                @if ($factor->is_required) <span class="text-red-600">*</span> @endif
                                <span class="text-xs text-zinc-500">· {{ rtrim(rtrim((string) $factor->weight, '0'), '.') }}%</span>
                                @if ($factor->description)
                                    <div class="text-xs text-zinc-500">{{ $factor->description }}</div>
                                @endif
                            </div>
                            <div class="flex gap-1" role="radiogroup" aria-label="{{ $factor->name }}">
                                @for ($i = 1; $i <= $max; $i++)
                                    <label @class([
                                        'grid size-9 place-items-center rounded-lg text-sm font-semibold ring-1 transition',
                                        'cursor-pointer hover:bg-brand-500/10' => $editable,
                                        'bg-brand-800 text-white ring-brand-800 dark:bg-white dark:text-brand-900' => (string) ($scores[$factor->id] ?? '') === (string) $i,
                                        'ring-zinc-500/25' => (string) ($scores[$factor->id] ?? '') !== (string) $i,
                                    ])>
                                        <input type="radio" class="sr-only" value="{{ $i }}" wire:model.live="scores.{{ $factor->id }}" @disabled(! $editable)>
                                        {{ $i }}
                                    </label>
                                @endfor
                            </div>
                        </div>
                        @error('scores.'.$factor->id) <flux:text class="text-sm !text-red-600">{{ $message }}</flux:text> @enderror
                        @if ($editable)
                            <x-form.input wire:model="notes.{{ $factor->id }}" :placeholder="__('Note (optional)')" />
                        @elseif ($notes[$factor->id] ?? '')
                            <flux:text class="text-sm">{{ $notes[$factor->id] }}</flux:text>
                        @endif
                    </div>
                @endforeach
                @error('scores') <flux:text class="text-sm !text-red-600">{{ $message }}</flux:text> @enderror

                <fieldset @disabled(! $editable) class="space-y-4">
                    <x-form.select wire:model="recommendation" :label="__('Recommendation')" :options="$recommendations" :placeholder="__('Choose…')" required />
                    <x-form.textarea wire:model="comment" :label="__('Comment')" rows="3" />
                </fieldset>
            </form>
        @endif

        @if ($editable)
            <x-slot:footer>
                <div class="flex gap-2">
                    <flux:button wire:click="saveDraft">{{ __('Save draft') }}</flux:button>
                    <flux:button variant="primary" x-on:click="$dispatch('confirm', {
                        title: @js(__('Send your scores?')),
                        message: @js(__('After sending you cannot change them.')),
                        confirmText: @js(__('Send')),
                        onConfirm: () => $wire.send(),
                    })">{{ __('Send scores') }}</flux:button>
                </div>
            </x-slot:footer>
        @endif
    </x-ui.modal>
</div>
