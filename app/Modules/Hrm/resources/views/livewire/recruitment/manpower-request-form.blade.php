<div>
    <x-ui.modal name="manpower-request-form" size="lg"
        :heading="$request ? __('Edit :no', ['no' => $request->number()]) : __('New manpower request')"
        :subheading="$editable ? __('Saved as a draft. Send it for approval from its page.') : __('Waiting for approval or already answered: it can no longer be changed.')">
        <form wire:submit="save" id="manpower-request-form" class="space-y-6">
            <fieldset @disabled(! $editable) class="space-y-6">
                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.select wire:model.live="form.org_unit_id" :label="__('Org unit')" :options="$units" :placeholder="__('Choose…')" required />
                    <x-form.select wire:model="form.designation_id" :label="__('Designation')" :options="$designations" :placeholder="__('Choose…')" required />
                    <x-form.select wire:model="form.position_id" :label="__('Position')" :options="$positions" :placeholder="__('None')"
                        :hint="__('Optional: the seat in the org unit')" />
                    <x-form.select wire:model="form.grade_id" :label="__('Grade')" :options="$grades" :placeholder="__('None')" />
                    <x-form.select wire:model="form.employment_type_id" :label="__('Employment type')" :options="$types" :placeholder="__('None')" />
                    <x-form.input wire:model="form.headcount" type="number" min="1" :label="__('Headcount')" required />
                </div>

                <div class="grid gap-4 sm:grid-cols-2">
                    <x-form.select wire:model.live="form.type" :label="__('Type')" :options="$requestTypes" required />
                    @if (($form['type'] ?? '') ===\App\Modules\Hrm\Enums\ManpowerRequestType::Replacement->value)
                        <x-form.select wire:model="form.replaces_employee_id" :label="__('Replaces')" :options="$employees"
                            :placeholder="$employees ? __('Choose…') : __('Choose the org unit first')" required />
                    @endif
                </div>

                <div class="grid gap-4 sm:grid-cols-3">
                    <x-form.input wire:model="form.salary_min" type="number" min="0" step="0.01" :label="__('Salary from')" :hint="__('Monthly gross')" />
                    <x-form.input wire:model="form.salary_max" type="number" min="0" step="0.01" :label="__('Salary to')" />
                    <x-form.date wire:model="form.needed_by" :label="__('Needed by')" />
                </div>

                <x-form.textarea wire:model="form.justification" :label="__('Justification')" :hint="__('Why these people are needed')" />
            </fieldset>

            @if ($request?->isDraft())
                <flux:separator />
                <flux:button size="sm" variant="ghost" icon="trash" class="!text-red-600" x-on:click="$dispatch('confirm', {
                    title: @js(__('Delete this request?')),
                    message: @js(__('Only possible while it is a draft.')),
                    confirmText: @js(__('Delete')),
                    onConfirm: () => $wire.delete(),
                })">{{ __('Delete') }}</flux:button>
            @endif
        </form>

        @if ($editable)
            <x-slot:footer>
                <flux:button type="submit" form="manpower-request-form" variant="primary">{{ __('Save draft') }}</flux:button>
            </x-slot:footer>
        @endif
    </x-ui.modal>
</div>
