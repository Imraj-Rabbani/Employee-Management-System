<div>
    <x-ui.modal :name="$modal" size="lg" :heading="$record ? __('Edit question set') : __('New question set')"
        :subheading="__('The questions interviewers score in a stage, each with its weight. The weights add up to 100%.')">
        <form wire:submit="save" id="{{ $modal }}" class="space-y-6">
            <div class="grid items-start gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" wire:model="form.name" :placeholder="__('e.g. Technical – Software')" />
                    <flux:error name="form.name" />
                </flux:field>
                <flux:switch wire:model="form.is_active" :label="__('Active')" :description="__('Only active sets can be chosen for a new stage')" />
            </div>

            <flux:field>
                <flux:label>{{ __('Description') }}</flux:label>
                <flux:textarea rows="2" wire:model="form.description" />
                <flux:error name="form.description" />
            </flux:field>

            {{-- The questions, in score-sheet order, each with its weight in this set. --}}
            <div class="space-y-3">
                <div class="flex flex-wrap items-center justify-between gap-2">
                    <flux:heading>{{ __('Questions') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:heading>
                    <div class="flex items-center gap-3 text-sm">
                        <span @class(['font-medium tabular-nums', 'text-green-700 dark:text-green-300' => $total === '100', 'text-amber-700 dark:text-amber-300' => $total !== '100'])>
                            {{ __('Total: :total%', ['total' => $total]) }}
                        </span>
                        @unless ($locked)
                            <flux:button size="xs" icon="scale" wire:click="splitEqually" :disabled="$items === []">{{ __('Split equally') }}</flux:button>
                        @endunless
                    </div>
                </div>

                @if ($locked)
                    <flux:callout variant="secondary" icon="lock-closed" :heading="__('An interview stage uses this set, so its questions and weights cannot change. Make a new set for different questions.')" />
                @endif

                @if ($items !== [])
                    <ol class="divide-y divide-zinc-500/10 rounded-lg ring-1 ring-zinc-500/15">
                        @foreach ($items as $index => $item)
                            <li class="flex items-center gap-3 px-3 py-2" wire:key="set-item-{{ $item['question_id'] }}">
                                <span class="w-5 shrink-0 text-xs tabular-nums text-zinc-500">{{ $index + 1 }}</span>
                                <span class="min-w-0 flex-1 text-sm">{{ $questions[$item['question_id']]->name ?? '?' }}</span>
                                <div class="w-28 shrink-0">
                                    <flux:input size="sm" type="number" min="0" max="100" step="0.01" wire:model.live.debounce.400ms="items.{{ $index }}.weight"
                                        :disabled="$locked" :aria-label="__('Weight (%)')" suffix="%" />
                                </div>
                                @unless ($locked)
                                    <div class="flex shrink-0">
                                        <flux:button size="xs" variant="ghost" icon="chevron-up" wire:click="moveItem({{ $index }}, -1)" :disabled="$loop->first" :aria-label="__('Move up')" />
                                        <flux:button size="xs" variant="ghost" icon="chevron-down" wire:click="moveItem({{ $index }}, 1)" :disabled="$loop->last" :aria-label="__('Move down')" />
                                        <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="removeItem({{ $index }})" :aria-label="__('Remove')" />
                                    </div>
                                @endunless
                            </li>
                            @error('items.'.$index.'.weight') <li class="px-3 pb-2 text-sm text-red-600">{{ $message }}</li> @enderror
                        @endforeach
                    </ol>
                @endif

                @unless ($locked)
                    <flux:field>
                        <flux:select wire:model.live="addQuestionId" :aria-label="__('Add question')">
                            <flux:select.option value="">{{ $addable->isNotEmpty() ? __('+ Add question…') : ($questions->isEmpty() ? __('Add questions in the Interview questions tab first') : __('All active questions are in this set')) }}</flux:select.option>
                            @foreach ($addable as $question)
                                <flux:select.option :value="$question->id">{{ $question->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </flux:field>
                @endunless
                <flux:error name="items" />
            </div>

            @if ($record)
                <flux:separator />
                <flux:button size="sm" variant="ghost" icon="trash" class="!text-red-600" x-on:click="$dispatch('confirm', {
                    title: {{ Js::from(__('Delete this question set?')) }},
                    message: {{ Js::from(__('Only possible when no interview stage uses it.')) }},
                    confirmText: {{ Js::from(__('Delete')) }},
                    onConfirm: () => $wire.delete(),
                })">{{ __('Delete') }}</flux:button>
            @endif
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="{{ $modal }}" variant="primary">{{ __('Save') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
