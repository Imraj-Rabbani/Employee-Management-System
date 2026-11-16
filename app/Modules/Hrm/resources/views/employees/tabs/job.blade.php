{{-- Profile → Job tab. "Edit" opens JobForm. --}}
@php
    // Supervisor / dotted-line manager as a link to their own profile (plain name if you may not open it).
    // withoutGlobalScope: the visibility scope would hide a supervisor from another unit and show "—" instead.
    $personLink = function ($relation) {
        $person = $relation->withoutGlobalScope(\App\Modules\Hrm\Scopes\VisibleToCurrentUser::class)->first();
        if (! $person) {
            return null;
        }
        if (auth()->user()?->cannot('view', $person)) {
            return $person->name;
        }

        return new \Illuminate\Support\HtmlString(sprintf(
            '<a href="%s" wire:navigate class="font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">%s</a>',
            e(route('hrm.employees.show', $person)),
            e($person->name.' ('.$person->code.')'),
        ));
    };
@endphp

<div class="space-y-4">
    @can('update', $employee)
        <div class="flex justify-end">
            <flux:button icon="pencil-square" x-on:click="$dispatch('edit-employee-job')">{{ __('Edit job details') }}</flux:button>
        </div>
        <livewire:hrm::employees.job-form :$employee />
    @endcan

    <div class="glass overflow-hidden rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ __('Placement') }}</flux:heading>
            </div>
        </div>

        <div class="p-6">
            <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                        __('Employee code') => $employee->code,
                        __('Attendance ID') => $employee->access_code,
                        __('Org unit') => $employee->orgUnit?->path(),
                        __('Designation') => $employee->designation?->name,
                        __('Grade') => $employee->grade?->name,
                        __('Position') => $employee->position?->name,
                        __('Branch') => $employee->branch?->name,
                        __('Cost center') => $employee->costCenter?->name,
                        __('Employment type') => $employee->employmentType?->name,
                        __('Supervisor') => $personLink($employee->supervisor()),
                        __('Dotted-line manager') => $personLink($employee->dottedLineManager()),
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
                <flux:heading size="lg">{{ __('Dates & status') }}</flux:heading>
            </div>
        </div>

        <div class="p-6">
            <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                        __('Joined') => format_date($employee->join_date),
                        __('Planned confirmation') => format_date($employee->planned_confirmation_date),
                        __('Confirmed on') => format_date($employee->confirmation_date),
                        __('Status') => $employee->status->label(),
                        __('Separated on') => format_date($employee->separation_date),
                        __('Payment method') => $employee->payment_method?->label(),
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

    {{-- 7.1: no own shift = the company's default shift. --}}
    @php $defaultShift = $employee->shift_id ? null : \App\Modules\Payroll\Models\Shift::default(); @endphp
    <div class="glass overflow-hidden rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ __('Attendance') }}</flux:heading>
            </div>
        </div>

        <div class="p-6">
            <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                        __('Shift') => $employee->shift?->optionLabel() ?? ($defaultShift ? __('Default (:shift)', ['shift' => $defaultShift->optionLabel()]) : null),
                        __('Works on a roster') => $employee->has_shifting ? __('Yes') : __('No'),
                        __('Weekend') => $employee->weekendProfile ? $employee->weekendProfile->name.' · '.$employee->weekendProfile->summary() : null,
                        __('Holiday calendar') => $employee->holidayProfile?->name,
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

    {{-- 8.1. The balances are on the Leave tab. --}}
    <div class="glass overflow-hidden rounded-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-white/50 px-6 py-4 dark:border-white/10">
            <div>
                <flux:heading size="lg">{{ __('Leave') }}</flux:heading>
            </div>
        </div>

        <div class="p-6">
            <dl class="grid gap-x-6 gap-y-4 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ([
                        __('Leave profile') => $employee->leaveProfile?->name,
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
