{{-- EncashmentTable list. PHP side: App\Modules\Hrm\Livewire\Leave\EncashmentTable --}}
@use('App\Modules\Hrm\Models\LeaveApplication')
<div class="glass overflow-hidden rounded-2xl">

    {{-- Toolbar --}}
    <div class="flex flex-wrap items-center gap-3 border-b border-white/50 p-4 dark:border-white/10">
        <div class="w-full sm:w-72">
            <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search…')" clearable />
        </div>

        <div class="w-full sm:w-44">
            <flux:select wire:model.live="status" :aria-label="__('Status')">
                <flux:select.option value="">{{ __('All') }}: {{ __('Status') }}</flux:select.option>
                @foreach ($statusOptions as $value => $label)
                    <flux:select.option value="{{ $value }}">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>

        @if ($search !== '' || $status !== '')
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
                        <button type="button" wire:click="sort('id')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('No.') }}
                            @if ($sortBy === 'id')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium">{{ __('Employee') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Leave type') }}</th>
                    <th class="px-4 py-3 font-medium whitespace-nowrap text-right">
                        <button type="button" wire:click="sort('days')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Days') }}
                            @if ($sortBy === 'days')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium text-right">{{ __('Amount') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Reason') }}</th>
                    <th class="px-4 py-3 font-medium">{{ __('Status') }}</th>
                    <th class="px-4 py-3 font-medium whitespace-nowrap">
                        <button type="button" wire:click="sort('created_at')" class="inline-flex items-center gap-1 hover:text-zinc-900 dark:hover:text-white">
                            {{ __('Asked') }}
                            @if ($sortBy === 'created_at')
                                <flux:icon :name="$sortDir === 'asc' ? 'chevron-up' : 'chevron-down'" variant="micro" />
                            @else
                                <flux:icon.chevron-up-down variant="micro" class="opacity-40" />
                            @endif
                        </button>
                    </th>

                    <th class="px-4 py-3 font-medium">{{ '' }}</th>
                </tr>
            </thead>

            <tbody>
                @foreach ($rows as $leaveEncashment)
                    <tr wire:key="leave-encashment-{{ $leaveEncashment->getKey() }}" class="border-b border-white/40 text-zinc-800 last:border-0 hover:bg-white/40 dark:border-white/5 dark:text-zinc-100 dark:hover:bg-white/5">
                        {{-- Opens the edit form (it listens for "edit-encashment"). --}}
                        <td class="px-4 py-3">
                            <button type="button" x-on:click="$dispatch('edit-encashment', { id: {{ $leaveEncashment->getKey() }} })" class="text-left font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">
                                {{ $leaveEncashment->number() }}
                            </button>
                        </td>
                        <td class="px-4 py-3">{{ $leaveEncashment->employee ? $leaveEncashment->employee->code.' · '.$leaveEncashment->employee->name : '—' }}</td>
                        <td class="px-4 py-3">{{ $leaveEncashment->leaveType->name ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">{{ LeaveApplication::daysLabel($leaveEncashment->days) }}</td>
                        <td class="px-4 py-3 text-right">{{ $leaveEncashment->amount ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $leaveEncashment->reason }}</td>
                        <td class="px-4 py-3">
                            <x-ui.badge :color="$leaveEncashment->statusBadge()[1]">{{ $leaveEncashment->statusBadge()[0] }}</x-ui.badge>
                        </td>
                        <td class="px-4 py-3">{{ format_date($leaveEncashment->created_at) }}</td>
                        <td class="px-4 py-3">
                            {{-- Withdraw: only while the request is still open and not paid. EncashmentForm listens for withdraw-encashment. --}}
                            @if ($leaveEncashment->isActive() && $leaveEncashment->paid_at === null)
                                <flux:button size="xs" variant="ghost" class="!text-red-600" x-on:click="$dispatch('confirm', {
                                    title: @js(__('Withdraw :no?', ['no' => $leaveEncashment->number()])),
                                    message: @js($leaveEncashment->approved_at ? __('The days go back to the balance.') : __('The approvers are no longer asked.')),
                                    confirmText: @js(__('Withdraw')),
                                    onConfirm: () => $dispatch('withdraw-encashment', { id: {{ $leaveEncashment->id }} }),
                                })">{{ __('Withdraw') }}</flux:button>
                            @endif
                        </td>
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
