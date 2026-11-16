{{-- Profile → Personal tab. "Edit" opens PersonalForm (one modal for all three cards). --}}
<div class="space-y-4">
    @can('update', $employee)
        <div class="flex justify-end">
            <flux:button icon="pencil-square" x-on:click="$dispatch('edit-employee-personal')">{{ __('Edit personal details') }}</flux:button>
        </div>
        <livewire:hrm::employees.personal-form :$employee />
    @elseif ($employee->user_id !== null && $employee->user_id === auth()->id())
        {{-- Your own record: changes go through approval. --}}
        <div class="flex justify-end">
            <flux:button icon="pencil-square" :href="route('ess.profile-requests')" wire:navigate>{{ __('Request a change') }}</flux:button>
        </div>
    @endcan

    <div class="glass overflow-hidden rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ __('Personal details') }}</flux:heading>
            </div>
        </div>

        <div class="p-6">
            <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
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
        </div>
    </div>

    <div class="glass overflow-hidden rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ __('Identity') }}</flux:heading>
            </div>
        </div>

        <div class="p-6">
            <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                        __('National ID') => $employee->national_id,
                        __('TIN') => $employee->tin,
                        __('Passport no.') => $employee->passport_no,
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
        </div>
    </div>

    <div class="glass overflow-hidden rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ __('Contact') }}</flux:heading>
            </div>
        </div>

        <div class="p-6">
            <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                        __('Mobile') => $employee->mobile,
                        __('Personal email') => $employee->personal_email,
                        __('Office email') => $employee->office_email,
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
        </div>
    </div>
</div>
