<div>
    <x-ui.modal name="applicant-form" size="lg"
        :heading="$applicant ? __('Edit :name', ['name' => $applicant->name]) : __('New applicant')"
        :subheading="$applicant ? null : __('Give an email or a phone number: they are used to find people already entered.')">
        <form wire:submit="save" id="applicant-form" class="space-y-6">
            @unless ($applicant)
                <flux:field>
                    <flux:label>{{ __('Applies to') }}</flux:label>
                    <flux:description>{{ __('Open postings that take applications today') }}</flux:description>
                    <flux:select wire:model="postingId">
                        <flux:select.option value="">{{ $postings ? __('No posting (talent pool only)') : __('No posting is taking applications') }}</flux:select.option>
                        @foreach ($postings as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="postingId" />
                </flux:field>
            @endunless

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('First name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input aria-required="true" wire:model="form.first_name" />
                    <flux:error name="form.first_name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Last name') }}</flux:label>
                    <flux:input wire:model="form.last_name" />
                    <flux:error name="form.last_name" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Email') }}</flux:label>
                    <flux:input wire:model.blur="form.email" type="email" />
                    <flux:error name="form.email" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Phone') }}</flux:label>
                    <flux:input :placeholder="__('e.g. 01711-000000')" wire:model.blur="form.phone" />
                    <flux:error name="form.phone" />
                </flux:field>
            </div>

            {{-- Duplicate check: shown as soon as an email / phone matches someone already entered. --}}
            @if ($duplicates->isNotEmpty())
                <div class="space-y-2 rounded-xl bg-amber-500/10 p-4 ring-1 ring-amber-500/30">
                    <flux:text class="font-medium text-amber-800 dark:text-amber-200">{{ __('Already entered with this email or phone:') }}</flux:text>
                    @foreach ($duplicates as $person)
                        <div class="flex flex-wrap items-center justify-between gap-2 text-sm" wire:key="dup-{{ $person->id }}">
                            <span>
                                <span class="font-medium">{{ $person->name }}</span>
                                <span class="text-zinc-500">· {{ $person->email ?? '—' }} · {{ $person->phone ?? '—' }}</span>
                            </span>
                            <flux:button size="xs" variant="primary" wire:click="useExisting({{ $person->id }})">
                                {{ $postingId !== '' && ! $applicant ? __('Use this person') : __('Open') }}
                            </flux:button>
                        </div>
                    @endforeach
                </div>
            @endif

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('Gender') }}</flux:label>
                    <flux:select wire:model="form.gender">
                        <flux:select.option value="">{{ __('Not set') }}</flux:select.option>
                        @foreach ($genders as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="form.gender" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Date of birth') }}</flux:label>
                    <flux:input type="date" wire:model="form.birth_date" />
                    <flux:error name="form.birth_date" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('National ID') }}</flux:label>
                    <flux:input wire:model="form.national_id" />
                    <flux:error name="form.national_id" />
                </flux:field>
            </div>
            <flux:field>
                <flux:label>{{ __('Address') }}</flux:label>
                <flux:input wire:model="form.address" />
                <flux:error name="form.address" />
            </flux:field>

            <flux:separator :text="__('Now')" />
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('Current employer') }}</flux:label>
                    <flux:input wire:model="form.current_employer" />
                    <flux:error name="form.current_employer" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Current designation') }}</flux:label>
                    <flux:input wire:model="form.current_designation" />
                    <flux:error name="form.current_designation" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Expected salary') }}</flux:label>
                    <flux:description>{{ __('Monthly gross') }}</flux:description>
                    <flux:input wire:model="form.expected_salary" type="number" min="0" step="0.01" />
                    <flux:error name="form.expected_salary" />
                </flux:field>
            </div>

            <div class="space-y-2">
                @if ($applicant?->cv_path)
                    <div class="flex items-center gap-2 text-sm">
                        <flux:icon.paper-clip variant="micro" class="text-zinc-500" />
                        <a href="{{ route('hrm.recruitment.applicants.cv', $applicant) }}" class="font-medium text-brand-800 underline-offset-4 dark:text-brand-200 underline decoration-current/50 hover:decoration-current hover:text-blue-600 dark:hover:text-blue-400">{{ $applicant->cv_name ?? __('CV') }}</a>
                        <flux:button size="xs" variant="ghost" class="!text-red-600" x-on:click="$dispatch('confirm', {
                            title: {{ Js::from(__('Remove the CV?')) }},
                            confirmText: {{ Js::from(__('Remove')) }},
                            onConfirm: () => $wire.removeCv(),
                        })">{{ __('Remove') }}</flux:button>
                    </div>
                @endif
                <flux:field>
                    <flux:label>{{ $applicant?->cv_path ? __('Replace CV') : __('CV') }}</flux:label>
                    <flux:description>{{ __('PDF or Word, up to 5 MB') }}</flux:description>
                    <flux:input type="file" wire:model="cv" accept=".pdf,.doc,.docx" />
                    <flux:text wire:loading wire:target="cv" class="mt-2 text-sm">{{ __('Uploading…') }}</flux:text>
                    <flux:error name="cv" />
                </flux:field>
                <flux:field>
                    <flux:label>{{ __('CV link') }}</flux:label>
                    <flux:input type="url" wire:model="form.cv_url" :placeholder="__('e.g. https://drive.google.com/…')" />
                    <flux:error name="form.cv_url" />
                </flux:field>
            </div>

            <flux:field>
                <flux:label>{{ __('Notes') }}</flux:label>
                <flux:textarea rows="3" wire:model="form.notes" />
                <flux:error name="form.notes" />
            </flux:field>

            @if ($applicant)
                <flux:separator />
                <flux:button size="sm" variant="ghost" icon="trash" class="!text-red-600" x-on:click="$dispatch('confirm', {
                    title: {{ Js::from(__('Delete this applicant?')) }},
                    message: {{ Js::from(__('Only possible when they have no applications. Their CV, education and work history are deleted too.')) }},
                    confirmText: {{ Js::from(__('Delete')) }},
                    onConfirm: () => $wire.delete(),
                })">{{ __('Delete') }}</flux:button>
            @endif
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="applicant-form" variant="primary">{{ $applicant ? __('Save') : __('Add applicant') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
