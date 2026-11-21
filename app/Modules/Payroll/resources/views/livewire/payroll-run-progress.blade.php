{{-- Progress of a run's calculation. PHP side: App\Modules\Payroll\Livewire\PayrollRunProgress --}}
<div>
    <x-ui.modal name="payroll-run-progress" :heading="$run ? __('Calculation of :code', ['code' => $run->code]) : __('Calculation')">
        {{-- wire:poll exists only while the run is Processing: the bar moves, then polling stops. --}}
        <div class="space-y-6" @if ($busy) wire:poll.2s @endif>
            @if ($run)
                <div class="flex flex-wrap items-center gap-3">
                    <x-ui.badge :color="$run->status->color()">{{ $run->status->label() }}</x-ui.badge>
                    <span class="text-sm text-zinc-600 dark:text-zinc-300">{{ $run->title() }}</span>
                </div>

                <div class="space-y-2">
                    <div class="flex items-baseline justify-between text-sm">
                        <span>{{ __(':done of :total employees', ['done' => $ok + $failed, 'total' => $total]) }}</span>
                        <span class="tabular-nums text-zinc-500 dark:text-zinc-400">{{ $percent }}%</span>
                    </div>
                    <div class="h-3 overflow-hidden rounded-full bg-zinc-200/70 dark:bg-white/10" role="progressbar" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $percent }}">
                        <div class="h-full rounded-full bg-brand-800 transition-all duration-500 dark:bg-brand-300" style="width: {{ $percent }}%"></div>
                    </div>
                    <div class="flex flex-wrap gap-x-4 gap-y-1 text-sm">
                        <span class="text-emerald-700 dark:text-emerald-300">{{ __(':n calculated', ['n' => $ok]) }}</span>
                        <span class="{{ $failed > 0 ? 'font-medium text-red-600 dark:text-red-400' : 'text-zinc-500 dark:text-zinc-400' }}">{{ __(':n failed', ['n' => $failed]) }}</span>
                        <span class="text-zinc-500 dark:text-zinc-400">{{ __(':n waiting', ['n' => $pending]) }}</span>
                        @if ($warned > 0)
                            <span class="text-amber-700 dark:text-amber-300">{{ trans_choice(':count with a warning|:count with warnings', $warned) }}</span>
                        @endif
                    </div>
                </div>

                @if ($busy && $ok + $failed === 0)
                    <flux:text class="text-sm">{{ __('Waiting for the queue worker. If this stays at 0%, start it with: php artisan queue:work') }}</flux:text>
                @endif

                @if ($failures->isNotEmpty())
                    <div>
                        <flux:heading size="sm">{{ __('Employees that could not be calculated') }}</flux:heading>
                        <ul class="mt-2 divide-y divide-zinc-200/70 rounded-xl border border-zinc-200/70 text-sm dark:divide-white/10 dark:border-white/10">
                            @foreach ($failures as $row)
                                <li class="px-4 py-3" wire:key="failure-{{ $row->id }}">
                                    <div class="font-medium">{{ $row->employee->code }} · {{ $row->employee->name }}</div>
                                    <div class="text-red-600 dark:text-red-400">{{ $row->error }}</div>
                                </li>
                            @endforeach
                        </ul>
                        @if ($moreFailures > 0)
                            <flux:text class="mt-2 text-sm">{{ __('and :n more.', ['n' => $moreFailures]) }}</flux:text>
                        @endif
                        <flux:text class="mt-2 text-sm">{{ __('Fix the cause (for example set the salary on the employee\'s Salary tab), then calculate the run again.') }}</flux:text>
                    </div>
                @endif
            @endif
        </div>
    </x-ui.modal>
</div>
