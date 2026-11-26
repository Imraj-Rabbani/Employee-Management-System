<div>
    <x-ui.modal name="interview-form" size="lg"
        :heading="$interview ? __('Change :stage', ['stage' => $interview->title()]) : ($schedule->count() > 1 ? __('Schedule :count interviews', ['count' => $schedule->count()]) : __('Schedule interview'))"
        :subheading="$interview ? $interview->application->applicant->name : ($schedule->count() === 1 ? $schedule->first()['name'] : __('Each applicant gets their own interview with the same panel.'))">
        <form wire:submit="save" id="interview-form" class="space-y-6">
            @unless ($interview)
                {{-- The stage: a new one (name + question set) or one this posting already has (its set comes with it). --}}
                <div class="space-y-4 rounded-xl bg-zinc-500/5 p-4">
                    <flux:field>
                        <flux:label>{{ __('Stage') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                        <flux:select wire:model.live="stageId" aria-required="true">
                            <flux:select.option value="new">{{ __('+ New stage') }}</flux:select.option>
                            @foreach ($stages as $stage)
                                <flux:select.option :value="$stage->id">{{ $stage->name }} · {{ $stage->questionSet->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="stageId" />
                    </flux:field>

                    @if ($stageId === 'new')
                        <div class="grid items-start gap-4 sm:grid-cols-2">
                            <flux:field>
                                <flux:label>{{ __('Stage name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                                <flux:input wire:model="stageName" aria-required="true" :placeholder="__('e.g. Stage 1, Technical round')" />
                                <flux:error name="stageName" />
                            </flux:field>
                            <flux:field>
                                <flux:label>{{ __('Question set') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                                <flux:select wire:model="questionSetId" aria-required="true">
                                    <flux:select.option value="">{{ $questionSets ? __('Choose…') : __('Make a question set in Recruitment → Config first') }}</flux:select.option>
                                    @foreach ($questionSets as $optionValue => $optionLabel)
                                        <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="questionSetId" />
                            </flux:field>
                        </div>
                        <flux:text class="text-xs">{{ __('The question set is fixed for the stage: everyone in it is scored on the same questions.') }}</flux:text>
                    @elseif ($chosenStage)
                        <flux:text class="text-sm">{{ __('Questions: :set', ['set' => $chosenStage->questionSet->name]) }}</flux:text>
                    @endif
                </div>
            @else
                <flux:text class="text-sm">{{ __('Stage: :stage · Questions: :set', ['stage' => $interview->title(), 'set' => $interview->stage?->questionSet?->name ?? '—']) }}</flux:text>
            @endunless

            <div class="grid gap-4 sm:grid-cols-[2fr_1fr]">
                <flux:field>
                    <flux:label>{{ __('Date and time') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" wire:model.live.blur="form.scheduled_at" type="datetime-local" />
                    <flux:error name="form.scheduled_at" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Minutes') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" wire:model.live.blur="form.duration_minutes" type="number" min="5" step="5" />
                    <flux:error name="form.duration_minutes" />
                </flux:field>
            </div>
            @if ($schedule->count() > 1 || $schedule->contains('already', true))
                {{-- Several applicants: back-to-back slots or one group session; the times below follow the choice. --}}
                @if ($schedule->count() > 1)
                    <flux:radio.group wire:model.live="timing" :label="__('Timing')" variant="segmented">
                        <flux:radio value="one_after_another" :label="__('One after another')" />
                        <flux:radio value="same_time" :label="__('All at the same time')" />
                    </flux:radio.group>
                @endif
                <ol class="divide-y divide-zinc-500/10 rounded-lg ring-1 ring-zinc-500/15">
                    @foreach ($schedule as $scheduled)
                        <li class="flex items-center justify-between gap-2 px-3 py-2 text-sm" wire:key="slot-{{ $loop->index }}">
                            <span class="font-medium">{{ $scheduled['name'] }}</span>
                            @if ($scheduled['already'])
                                <span class="text-xs font-medium text-red-600 dark:text-red-400">{{ __('Already in this stage: will be skipped') }}</span>
                            @else
                                <span class="tabular-nums text-zinc-500">{{ $scheduled['at'] }}</span>
                            @endif
                        </li>
                    @endforeach
                </ol>
            @endif

            <flux:field>
                <flux:label>{{ __('Where') }}</flux:label>
                <flux:description>{{ __('A room, an address or a video-call link') }}</flux:description>
                <flux:input wire:model="form.location" />
                <flux:error name="form.location" />
            </flux:field>

            <flux:field>
                <flux:label>{{ __('Note for the panel') }}</flux:label>
                <flux:textarea wire:model="form.notes" rows="2" />
                <flux:error name="form.notes" />
            </flux:field>

            {{-- The panel: search → Add. Chosen people are listed with Remove. --}}
            <div class="space-y-2">
                <flux:label>{{ __('Panel') }} <span class="text-red-600">*</span></flux:label>

                @foreach ($chosen as $employee)
                    @php $sent = $interview?->panelists->firstWhere('employee_id', $employee->id)?->isSubmitted(); @endphp
                    <div class="flex items-center justify-between gap-2 rounded-lg bg-zinc-500/5 px-3 py-2 text-sm" wire:key="chosen-{{ $employee->id }}">
                        <span>
                            <span class="font-medium">{{ $employee->name }}</span>
                            <span class="text-zinc-500">· {{ $employee->code }}{{ $employee->designation ? ' · '.$employee->designation->name : '' }}</span>
                        </span>
                        @if ($sent)
                            <x-ui.badge color="success">{{ __('Scores sent') }}</x-ui.badge>
                        @else
                            <flux:button size="xs" variant="ghost" icon="x-mark" wire:click="removePanelist({{ $employee->id }})">{{ __('Remove') }}</flux:button>
                        @endif
                    </div>
                @endforeach

                <flux:field>
                    <flux:input wire:model.live.debounce.300ms="search" :placeholder="__('Search an employee by name or code…')" icon="magnifying-glass" />
                    <flux:error name="search" />
                </flux:field>
                @if ($results->isNotEmpty())
                    <div class="divide-y divide-zinc-500/10 rounded-lg ring-1 ring-zinc-500/15">
                        @foreach ($results as $employee)
                            <button type="button" wire:click="addPanelist({{ $employee->id }})" wire:key="result-{{ $employee->id }}"
                                class="flex w-full items-center justify-between gap-2 px-3 py-2 text-left text-sm hover:bg-zinc-500/5">
                                <span>{{ $employee->name }} <span class="text-zinc-500">· {{ $employee->code }}</span></span>
                                <flux:icon.plus variant="micro" class="text-zinc-500" />
                            </button>
                        @endforeach
                    </div>
                @elseif (trim($search) !== '')
                    <flux:text class="text-xs">{{ __('No active employee found.') }}</flux:text>
                @endif

                @error('panel')
                    <flux:text class="text-sm !text-red-600">{{ $message }}</flux:text>
                @enderror
            </div>
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="interview-form" variant="primary">{{ $interview ? __('Save and tell the panel') : __('Schedule and tell the panel') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
