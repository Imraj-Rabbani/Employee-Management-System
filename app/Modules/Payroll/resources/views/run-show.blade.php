{{--
    Payroll → Payroll runs → one run: review before submitting (PR-04). The route passes $run (checked with visibleTo).
    Header with totals and actions (PayrollRunSummary), then two tabs: the register (PayrollRegister) and the
    exception list (PayrollExceptions). Clicking an employee anywhere opens their breakdown (PayrollRunEmployeeDetail).
--}}
<x-layouts.app :title="$run->code">
    <div class="space-y-4" x-data="{ tab: new URLSearchParams(location.search).get('tab') === 'exceptions' ? 'exceptions' : 'register' }"
        x-init="$watch('tab', t => { const u = new URL(location); t === 'register' ? u.searchParams.delete('tab') : u.searchParams.set('tab', t); history.replaceState(null, '', u) })">
        <flux:link :href="route('payroll.runs')" wire:navigate class="inline-flex items-center gap-1 text-sm">
            <flux:icon.arrow-left variant="micro" /> {{ __('All payroll runs') }}
        </flux:link>

        <livewire:payroll::payroll-run-summary :run-id="$run->id" />

        <div class="flex gap-1 border-b border-white/50 dark:border-white/10" role="tablist">
            @foreach (['register' => __('Register'), 'exceptions' => __('Exceptions')] as $tabKey => $tabLabel)
                <button type="button" role="tab" x-on:click="tab = '{{ $tabKey }}'" x-bind:aria-selected="tab === '{{ $tabKey }}'"
                    x-bind:class="tab === '{{ $tabKey }}' ? 'border-brand-800 text-brand-900 dark:border-brand-200 dark:text-white' : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:text-zinc-400 dark:hover:text-zinc-100'"
                    class="-mb-px border-b-2 px-4 py-2 text-sm font-medium">{{ $tabLabel }}</button>
            @endforeach
        </div>

        <div x-show="tab === 'register'"><livewire:payroll::payroll-register :run-id="$run->id" /></div>
        <div x-show="tab === 'exceptions'" x-cloak><livewire:payroll::payroll-exceptions :run-id="$run->id" /></div>

        <livewire:payroll::payroll-run-employee-detail />
        <livewire:payroll::payroll-run-progress />
        @can('payroll.run.process')
            <livewire:payroll::payroll-run-form />
        @endcan
    </div>
</x-layouts.app>
