{{--
    HRM → Employees → one employee's profile. [EMP-01]
    The route passes $employee, $tabs (key => label) and $tab (the open one).
    Each tab is a real link (?tab=job), so it can be bookmarked; its content lives in employees/tabs/{key}.blade.php.
--}}
<x-layouts.app :title="$employee->name">
    <div class="space-y-4">
        <flux:link :href="route('hrm.employees.index')" wire:navigate class="inline-flex items-center gap-1 text-sm">
            <flux:icon.arrow-left variant="micro" /> {{ __('All employees') }}
        </flux:link>

        {{-- Header: who this is at a glance. --}}
        <x-ui.card>
            <div class="flex flex-wrap items-center gap-5">
                <div class="flex flex-col items-center gap-1">
                    <flux:avatar size="xl" :name="$employee->name" :src="$employee->photo_url" />
                    @can('update', $employee)
                        <flux:button size="xs" variant="ghost" icon="camera" x-on:click="$dispatch('edit-employee-photo')">{{ __('Photo') }}</flux:button>
                    @endcan
                </div>

                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <flux:heading size="xl">{{ $employee->name }}</flux:heading>
                        <x-ui.badge :color="$employee->status->color()">{{ $employee->status->label() }}</x-ui.badge>
                    </div>
                    @if ($employee->name_local)
                        <flux:text>{{ $employee->name_local }}</flux:text>
                    @endif
                    <flux:text class="mt-1">
                        {{ $employee->code }}
                        · {{ $employee->designation->name ?? __('No designation') }}
                        · {{ $employee->orgUnit?->path() ?? __('No org unit') }}
                    </flux:text>
                </div>
            </div>
        </x-ui.card>

        @can('update', $employee)
            <livewire:hrm::employees.photo-form :$employee />
        @endcan

        {{-- Tabs (same look as Organization). --}}
        <nav class="flex flex-wrap gap-1 border-b border-zinc-200 dark:border-white/10" aria-label="{{ __('Profile sections') }}">
            @foreach ($tabs as $key => $label)
                <a href="{{ route('hrm.employees.show', ['employee' => $employee, 'tab' => $key]) }}" wire:navigate @class([
                    '-mb-px border-b-2 px-3 py-2 text-sm font-medium transition',
                    'border-brand-800 text-brand-900 dark:border-white dark:text-white' => $key === $tab,
                    'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $key !== $tab,
                ]) @if ($key === $tab) aria-current="page" @endif>{{ $label }}</a>
            @endforeach
        </nav>

        @include('hrm::employees.tabs.'.$tab)
    </div>
</x-layouts.app>
