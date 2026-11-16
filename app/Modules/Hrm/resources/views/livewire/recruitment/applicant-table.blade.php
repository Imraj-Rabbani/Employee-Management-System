{{-- ApplicantTable list. PHP side: App\Modules\Hrm\Livewire\Recruitment\ApplicantTable --}}
<div class="glass overflow-hidden rounded-2xl">

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-3 border-b border-white/50 p-4 dark:border-white/10">
        <div class="w-full sm:w-72">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search…')" clearable />
        </div>

        @if ($search !== '')
            <flux:button wire:click="clearFilters" variant="ghost" size="sm" icon="x-mark">{{ __('Clear') }}</flux:button>
        @endif

        <flux:spacer />

        <flux:button wire:click="export" size="sm" icon="arrow-down-tray">{{ __('Export') }}</flux:button>
    </div>

    {{-- The table --}}
    <div class="overflow-x-auto" wire:loading.class="opacity-60">
        <table class="min-w-full text-sm">
            <thead>
                <tr class="border-b border-white/50 text-left text-zinc-600 dark:border-white/10 dark:text-zinc-300">
                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('first_name')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Name') }}
                            @if ($sortBy === 'first_name')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium">{{ __('Email') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Phone') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Current employer') }}</th>
                    <th class="px-4 py-3 font-medium whitespace-nowrap text-right">
                        <button type="button" wire:click="sort('applications_count')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Applications') }}
                            @if ($sortBy === 'applications_count')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium">{{ __('CV') }}</th>
                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('created_at')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Added') }}
                            @if ($sortBy === 'created_at')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                </tr>
            </thead>

            <tbody>
                @foreach ($rows as $applicant)
                    <tr wire:key="applicant-{{ $applicant->getKey() }}" class="border-b border-white/40 text-zinc-800 last:border-0 hover:bg-white/40 dark:border-white/5 dark:text-zinc-100 dark:hover:bg-white/5">
                        <td class="px-4 py-3">
                            <a href="{{ route('hrm.recruitment.applicants.show', $applicant) }}" wire:navigate class="font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $applicant->name }}</a>
                        </td>
                        <td class="px-4 py-3">{{ $applicant->email }}</td>
                        <td class="px-4 py-3">{{ $applicant->phone }}</td>
                        <td class="px-4 py-3">{{ $applicant->current_employer }}</td>
                        <td class="px-4 py-3 text-right">{{ $applicant->applications_count }}</td>
                        <td class="px-4 py-3">{{ $applicant->cv_path ? __('Yes') : '' }}</td>
                        <td class="px-4 py-3">{{ format_date($applicant->created_at) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        @if ($rows->isEmpty())
            <div class="px-6 py-12 text-center">
                <flux:heading size="lg">{{ __('No results') }}</flux:heading>
            </div>
        @endif
    </div>

    {{-- Footer: page size and page links --}}
    <div class="flex flex-wrap items-center gap-4 border-t border-white/50 px-6 py-4 text-sm text-zinc-600 dark:border-white/10 dark:text-zinc-300">
        <div class="flex items-center gap-2">
            <span>{{ __('Rows per page') }}</span>
            <div class="w-20">
                <flux:select wire:model.live="perPage" size="sm" :aria-label="__('Rows per page')">
                    <flux:select.option value="10">10</flux:select.option>
                    <flux:select.option value="25">25</flux:select.option>
                    <flux:select.option value="50">50</flux:select.option>
                    <flux:select.option value="100">100</flux:select.option>
                </flux:select>
            </div>
        </div>

        <div class="ms-auto">{{ $rows->links() }}</div>
    </div>
</div>
