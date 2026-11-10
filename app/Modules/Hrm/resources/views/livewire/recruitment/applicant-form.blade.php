<div>
    <x-ui.modal name="applicant-form" size="lg"
        :heading="$applicant ? __('Edit :name', ['name' => $applicant->name]) : __('New applicant')"
        :subheading="$applicant ? null : __('Give an email or a phone number: they are used to find people already entered.')">
        <form wire:submit="save" id="applicant-form" class="space-y-6">
            @unless ($applicant)
                <x-form.select wire:model="postingId" :label="__('Applies to')" :options="$postings"
                    :placeholder="$postings ? __('No posting (talent pool only)') : __('No posting is taking applications')"
                    :hint="__('Open postings that take applications today')" />
            @endunless

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.input wire:model="form.first_name" :label="__('First name')" required />
                <x-form.input wire:model="form.last_name" :label="__('Last name')" />
                <x-form.input wire:model.blur="form.email" type="email" :label="__('Email')" />
                <x-form.input wire:model.blur="form.phone" :label="__('Phone')" :hint="__('e.g. 01711-000000')" />
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
                <x-form.select wire:model="form.gender" :label="__('Gender')" :options="$genders" :placeholder="__('Not set')" />
                <x-form.date wire:model="form.birth_date" :label="__('Date of birth')" />
                <x-form.input wire:model="form.national_id" :label="__('National ID')" />
            </div>
            <x-form.input wire:model="form.address" :label="__('Address')" />

            <flux:separator :text="__('Now')" />
            <div class="grid gap-4 sm:grid-cols-3">
                <x-form.input wire:model="form.current_employer" :label="__('Current employer')" />
                <x-form.input wire:model="form.current_designation" :label="__('Current designation')" />
                <x-form.input wire:model="form.expected_salary" type="number" min="0" step="0.01" :label="__('Expected salary')" :hint="__('Monthly gross')" />
            </div>

            <div class="space-y-2">
                @if ($applicant?->cv_path)
                    <div class="flex items-center gap-2 text-sm">
                        <flux:icon.paper-clip variant="micro" class="text-zinc-500" />
                        <a href="{{ route('hrm.recruitment.applicants.cv', $applicant) }}" class="font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $applicant->cv_name ?? __('CV') }}</a>
                        <flux:button size="xs" variant="ghost" class="!text-red-600" x-on:click="$dispatch('confirm', {
                            title: @js(__('Remove the CV?')),
                            confirmText: @js(__('Remove')),
                            onConfirm: () => $wire.removeCv(),
                        })">{{ __('Remove') }}</flux:button>
                    </div>
                @endif
                <x-form.file wire:model="cv" :label="$applicant?->cv_path ? __('Replace CV') : __('CV')" :hint="__('PDF or Word, up to 5 MB')" accept=".pdf,.doc,.docx" />
            </div>

            <x-form.textarea wire:model="form.notes" :label="__('Notes')" />

            @if ($applicant)
                <flux:separator />
                <flux:button size="sm" variant="ghost" icon="trash" class="!text-red-600" x-on:click="$dispatch('confirm', {
                    title: @js(__('Delete this applicant?')),
                    message: @js(__('Only possible when they have no applications. Their CV, education and work history are deleted too.')),
                    confirmText: @js(__('Delete')),
                    onConfirm: () => $wire.delete(),
                })">{{ __('Delete') }}</flux:button>
            @endif
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="applicant-form" variant="primary">{{ $applicant ? __('Save') : __('Add applicant') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
