{{-- New / Edit pay scale profile (PayScaleProfileForm). The payroll run (Phase 10) follows these rules. --}}
<x-form.lookup-modal :$modal :$record :heading="$record ? __('Edit pay scale profile') : __('New pay scale profile')"
    :deleteMessage="__('Not possible while employees use it. Make it inactive instead.')">
    <div class="grid gap-4 sm:grid-cols-3">
        <x-form.input wire:model="form.code" :label="__('Code')" required />
        <x-form.input wire:model="form.name" :label="__('Name')" required class="sm:col-span-2" :hint="__('e.g. Regular staff, Workers')" />
    </div>

    <x-form.toggle wire:model="form.pays_arrear" :label="__('Pay arrears')"
        :hint="__('Back-dated salary changes, and the days of late joiners, are paid in a later run.')" />
    <x-form.input wire:model="form.threshold_day" type="number" min="0" max="28" :label="__('Threshold day')" required
        :hint="__('Who joins after this day of the month is first paid in the next month\'s run. 0 = no threshold.')" />
    <x-form.toggle wire:model.live="form.deduct_lwp" :label="__('Deduct leave without pay')" />
    @if ($form['deduct_lwp'] ?? true)
        <x-form.toggle wire:model="form.ignore_late_lwp" :label="__('Except LWP from late attendance')"
            :hint="__('LWP that comes from lates (e.g. 3 lates = 1 day) is not deducted.')" />
    @endif
    <x-form.textarea wire:model="form.detail" :label="__('Detail')" rows="2" />
    <x-form.toggle wire:model="form.is_active" :label="__('Active')" />
</x-form.lookup-modal>
