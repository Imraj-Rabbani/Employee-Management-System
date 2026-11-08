{{-- Profile → Personal tab. "Edit" opens PersonalForm (one modal for all three cards). --}}
<div class="space-y-4">
    @can('update', $employee)
        <div class="flex justify-end">
            <flux:button icon="pencil-square" x-on:click="$dispatch('edit-employee-personal')">{{ __('Edit personal details') }}</flux:button>
        </div>
        <livewire:hrm::employees.personal-form :$employee />
    @endcan

    <x-ui.card :heading="__('Personal details')">
        <x-ui.details :items="[
            __('Title') => $employee->title,
            __('First name') => $employee->first_name,
            __('Last name') => $employee->last_name,
            __('Name (local)') => $employee->name_local,
            __('Gender') => $employee->gender?->label(),
            __('Date of birth') => format_date($employee->birth_date),
            __('Marital status') => $employee->marital_status?->label(),
            __('Blood group') => $employee->blood_group?->label(),
            __('Religion') => $employee->religion?->label(),
            __('Nationality') => $employee->nationality,
        ]" />
    </x-ui.card>

    <x-ui.card :heading="__('Identity')">
        <x-ui.details :items="[
            __('National ID') => $employee->national_id,
            __('TIN') => $employee->tin,
            __('Passport no.') => $employee->passport_no,
        ]" />
    </x-ui.card>

    <x-ui.card :heading="__('Contact')">
        <x-ui.details :items="[
            __('Mobile') => $employee->mobile,
            __('Personal email') => $employee->personal_email,
            __('Office email') => $employee->office_email,
        ]" />
    </x-ui.card>
</div>
