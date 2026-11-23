{{-- What to check before submitting. PHP side: App\Modules\Payroll\Livewire\PayrollExceptions --}}
<div class="space-y-4">
    @forelse ($groups as $key => $group)
        <div class="glass overflow-hidden rounded-2xl" wire:key="exceptions-{{ $key }}">
            <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/50 px-6 py-4 dark:border-white/10">
                <div>
                    <flux:heading size="lg">{{ $group['label'] }}</flux:heading>
                    <flux:text class="mt-1">{{ $group['hint'] }}</flux:text>
                </div>
                <x-ui.badge :color="$group['color']">{{ count($group['items']) }}</x-ui.badge>
            </div>
            <ul class="divide-y divide-white/40 text-sm dark:divide-white/5">
                @foreach (array_slice($group['items'], 0, $perGroup) as $i => $item)
                    <li class="flex flex-wrap items-baseline gap-x-4 gap-y-1 px-6 py-3" wire:key="exceptions-{{ $key }}-{{ $i }}">
                        <button type="button" x-on:click="$dispatch('show-payroll-employee', { id: {{ $item['row']->id }} })" class="shrink-0 font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">
                            {{ $item['row']->employee->code ?? '?' }} · {{ $item['row']->employee->name ?? '' }}
                        </button>
                        <span class="text-zinc-700 dark:text-zinc-200">{{ $item['message'] }}</span>
                    </li>
                @endforeach
                @if (count($group['items']) > $perGroup)
                    <li class="px-6 py-3 text-zinc-500 dark:text-zinc-400">{{ __('and :n more (use the register\'s filter).', ['n' => count($group['items']) - $perGroup]) }}</li>
                @endif
            </ul>
        </div>
    @empty
        <div class="glass rounded-2xl px-6 py-12 text-center">
            <flux:heading size="lg">{{ __('Nothing to check') }}</flux:heading>
            <flux:text class="mt-1">{{ __('Every employee is calculated, without warnings or big changes.') }}</flux:text>
        </div>
    @endforelse
</div>
