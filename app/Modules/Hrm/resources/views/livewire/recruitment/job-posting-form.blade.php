<div>
    <x-ui.modal name="job-posting-form" size="lg"
        :heading="$posting ? __('Edit :no', ['no' => $posting->number()]) : __('New job posting')"
        :subheading="$editable ? null : __('This posting is closed. Reopen it first to change it.')">
        <form wire:submit="save" id="job-posting-form" class="space-y-6">
            <fieldset @disabled(! $editable) class="space-y-6">
                @if ($posting)
                    <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                        @foreach ([
                                __('Manpower request') => $posting->manpowerRequest->number(),
                                __('For') => $posting->manpowerRequest->headcount.' × '.($posting->manpowerRequest->designation->name ?? '?').' ('.($posting->manpowerRequest->entity->name ?? '?').')',
                                __('Status') => $posting->status->label(),
                        ] as $detailLabel => $detailValue)
                            <div>
                                <dt class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $detailLabel }}</dt>
                                <dd class="mt-1 text-sm text-zinc-900 dark:text-zinc-100">
                                    @if ($detailValue === null || $detailValue === '')
                                        <span class="text-zinc-400">—</span>
                                    @else
                                        {{ $detailValue }}
                                    @endif
                                </dd>
                            </div>
                        @endforeach
                    </dl>
                @else
                    <flux:field>
                        <flux:label>{{ __('Manpower request') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                        <flux:description>{{ __('Approved requests that still have openings to advertise') }}</flux:description>
                        <flux:select aria-required="true" wire:model.live="form.manpower_request_id">
                            <flux:select.option value="">{{ $requests ? __('Choose…') : __('No approved request with openings left') }}</flux:select.option>
                            @foreach ($requests as $optionValue => $optionLabel)
                                <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="form.manpower_request_id" />
                    </flux:field>
                @endif

                <flux:field>
                    <flux:label>{{ __('Title') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:description>{{ __('As applicants will see it') }}</flux:description>
                    <flux:input aria-required="true" wire:model="form.title" />
                    <flux:error name="form.title" />
                </flux:field>
                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:field>
                        <flux:label>{{ __('Openings') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                        <flux:input aria-required="true" wire:model="form.openings" type="number" min="1" />
                        <flux:error name="form.openings" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Opens') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                        <flux:input type="date" aria-required="true" wire:model="form.open_date" />
                        <flux:error name="form.open_date" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Closes') }}</flux:label>
                        <flux:description>{{ __('Empty = until closed by hand') }}</flux:description>
                        <flux:input type="date" wire:model="form.close_date" />
                        <flux:error name="form.close_date" />
                    </flux:field>
                </div>
                <flux:field>
                    <flux:label>{{ __('Description') }}</flux:label>
                    <flux:description>{{ __('What the person will do') }}</flux:description>
                    <flux:textarea wire:model="form.description" rows="5" />
                    <flux:error name="form.description" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Requirements') }}</flux:label>
                    <flux:description>{{ __('Education, experience, skills') }}</flux:description>
                    <flux:textarea wire:model="form.requirements" rows="5" />
                    <flux:error name="form.requirements" />
                </flux:field>
            </fieldset>

            <fieldset @disabled(! $editable) class="space-y-4">
                <legend class="mb-2 text-sm font-medium text-zinc-800 dark:text-white">{{ __('Online application form') }}</legend>
                <flux:text class="text-xs">{{ __('Optional. If people apply through a Google Form, keep its links here; the answers are brought in with Import (the guide there lists the questions the form should ask).') }}</flux:text>
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:field>
                        <flux:label>{{ __('Form link') }}</flux:label>
                        <flux:input type="url" wire:model="form.form_url" :placeholder="__('e.g. https://forms.gle/…')" />
                        <flux:error name="form.form_url" />
                    </flux:field>
                    <flux:field>
                        <flux:label>{{ __('Responses sheet link') }}</flux:label>
                        <flux:input type="url" wire:model="form.responses_url" :placeholder="__('e.g. https://docs.google.com/spreadsheets/…')" />
                        <flux:error name="form.responses_url" />
                    </flux:field>
                </div>
            </fieldset>

            @if ($posting?->status === \App\Modules\Hrm\Enums\JobPostingStatus::Draft)
                <flux:separator />
                <flux:button size="sm" variant="ghost" icon="trash" class="!text-red-600" x-on:click="$dispatch('confirm', {
                    title: @js(__('Delete this posting?')),
                    message: @js(__('Only possible while it is a draft.')),
                    confirmText: @js(__('Delete')),
                    onConfirm: () => $wire.delete(),
                })">{{ __('Delete') }}</flux:button>
            @endif
        </form>

        @if ($editable)
            <x-slot:footer>
                <flux:button type="submit" form="job-posting-form" variant="primary">{{ __('Save') }}</flux:button>
            </x-slot:footer>
        @endif
    </x-ui.modal>
</div>
