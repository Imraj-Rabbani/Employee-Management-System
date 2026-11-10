<div>
    <x-ui.modal name="job-posting-form" size="lg"
        :heading="$posting ? __('Edit :no', ['no' => $posting->number()]) : __('New job posting')"
        :subheading="$editable ? null : __('This posting is closed. Reopen it first to change it.')">
        <form wire:submit="save" id="job-posting-form" class="space-y-6">
            <fieldset @disabled(! $editable) class="space-y-6">
                @if ($posting)
                    <x-ui.details :items="[
                        __('Manpower request') => $posting->manpowerRequest->number(),
                        __('For') => $posting->manpowerRequest->headcount.' × '.($posting->manpowerRequest->designation->name ?? '?').' ('.($posting->manpowerRequest->orgUnit->name ?? '?').')',
                        __('Status') => $posting->status->label(),
                    ]" />
                @else
                    <x-form.select wire:model.live="form.manpower_request_id" :label="__('Manpower request')" :options="$requests"
                        :placeholder="$requests ? __('Choose…') : __('No approved request with openings left')" required
                        :hint="__('Approved requests that still have openings to advertise')" />
                @endif

                <x-form.input wire:model="form.title" :label="__('Title')" required :hint="__('As applicants will see it')" />
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-form.input wire:model="form.openings" type="number" min="1" :label="__('Openings')" required />
                    <x-form.date wire:model="form.open_date" :label="__('Opens')" required />
                    <x-form.date wire:model="form.close_date" :label="__('Closes')" :hint="__('Empty = until closed by hand')" />
                </div>
                <x-form.textarea wire:model="form.description" :label="__('Description')" rows="5" :hint="__('What the person will do')" />
                <x-form.textarea wire:model="form.requirements" :label="__('Requirements')" rows="5" :hint="__('Education, experience, skills')" />
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
