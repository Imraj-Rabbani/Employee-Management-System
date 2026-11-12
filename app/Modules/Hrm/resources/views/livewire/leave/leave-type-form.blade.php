{{-- New / Edit leave type (LeaveTypeForm). Empty number = no limit. Switch-dependent fields only show when the switch is on. --}}
<x-form.lookup-modal :$modal :$record size="lg" :heading="$record ? __('Edit leave type') : __('New leave type')"
    :deleteMessage="__('Not possible while a leave profile contains it. Make it inactive instead.')">
    <div class="grid gap-4 sm:grid-cols-[1fr_2fr_1fr]">
        <x-form.input wire:model="form.code" :label="__('Code')" required :hint="__('e.g. CL, SL, EL')" />
        <x-form.input wire:model="form.name" :label="__('Name')" required :hint="__('e.g. Casual leave')" />
        <x-form.input type="number" wire:model="form.sequence" :label="__('Order')" required :hint="__('In lists and reports.')" />
    </div>
    <x-form.textarea wire:model="form.detail" :label="__('Detail')" rows="2" />

    <flux:separator :text="__('Who may take it')" />
    <div class="grid gap-4 sm:grid-cols-2">
        <x-form.select wire:model="form.gender" :label="__('Gender')" :options="$genders" :placeholder="__('Everyone')" />
        <x-form.select wire:model="form.religion" :label="__('Religion')" :options="$religions" :placeholder="__('Everyone')" />
        <x-form.input type="number" wire:model="form.eligible_after_months" :label="__('Eligible after (months)')" required :hint="__('0 = from the first day.')" />
        <x-form.select wire:model="form.eligible_from" :label="__('Counted from')" :options="$eligibleFrom" required />
        <x-form.input type="number" wire:model="form.max_times_in_service" :label="__('Times in whole service')" :hint="__('e.g. 2 for maternity leave. Empty = no limit.')" />
    </div>
    <x-form.toggle wire:model="form.hr_only" :label="__('HR only')" :hint="__('Not offered in Self Service: only HR enters this leave.')" />

    <flux:separator :text="__('One application')" />
    <div class="grid gap-4 sm:grid-cols-3">
        <x-form.input type="number" step="0.5" wire:model="form.min_days" :label="__('Minimum days')" :hint="__('Empty = no minimum.')" />
        <x-form.input type="number" step="0.5" wire:model="form.max_days" :label="__('Maximum days')" :hint="__('Empty = no maximum.')" />
        <x-form.input type="number" wire:model="form.max_calendar_days" :label="__('Maximum calendar days')" :hint="__('From–to, weekends included.')" />
        <x-form.input type="number" wire:model="form.notice_days" :label="__('Notice (days)')" required :hint="__('Apply at least this many days before.')" />
        <x-form.input type="number" step="0.5" wire:model="form.attachment_after_days" :label="__('Attachment for more than (days)')" :hint="__('0 = always. Empty = never.')" />
        <x-form.select wire:model="form.bridging" :label="__('Days off next to the leave')" :options="$bridgings" required
            :hint="__('Weekends / holidays touching the leave (sandwich rule).')" />
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <x-form.toggle wire:model="form.allow_half_day" :label="__('Half day allowed')" />
        <x-form.toggle wire:model="form.exclude_holidays" :label="__('Do not count weekends and holidays')" :hint="__('Only working days inside the leave are taken.')" />
        <x-form.toggle wire:model="form.reason_required" :label="__('Reason required')" />
        <div class="space-y-3">
            <x-form.toggle wire:model="form.allow_post_facto" :label="__('May apply afterwards')" :hint="__('After the leave has started (post facto).')" />
            <div x-show="$wire.form.allow_post_facto" x-cloak>
                <x-form.input type="number" wire:model="form.post_facto_days" :label="__('Up to days after')" :hint="__('Empty = any time.')" />
            </div>
        </div>
    </div>

    <flux:separator :text="__('Balance and earning')" />
    <div class="grid gap-4 sm:grid-cols-2">
        <x-form.select wire:model="form.earn_type" :label="__('Earned')" :options="$earnTypes" required
            :hint="__('The days per year come from the leave profile.')" />
        <div x-show="$wire.form.earn_type == {{ $perDaysWorked }}" x-cloak>
            <x-form.input type="number" step="0.01" wire:model="form.earn_per_days_worked" :label="__('Days worked per earned day')" :hint="__('e.g. 18')" />
        </div>
        <x-form.input type="number" step="0.5" wire:model="form.max_balance" :label="__('Maximum balance')" :hint="__('No more than this can be available. Empty = no limit.')" />
    </div>
    <div class="grid gap-4 sm:grid-cols-2">
        <x-form.toggle wire:model="form.requires_balance" :label="__('Needs a balance')" :hint="__('Cannot be taken beyond the days left.')" />
        <x-form.toggle wire:model="form.is_lwp" :label="__('Without pay')" :hint="__('Payroll deducts these days (LWP).')" />
    </div>

    <flux:separator :text="__('Year end and encashment')" />
    <div class="grid gap-4 sm:grid-cols-2">
        <div class="space-y-3">
            <x-form.toggle wire:model="form.can_carry_forward" :label="__('Carry forward')" :hint="__('Unused days move to the next year.')" />
            <div x-show="$wire.form.can_carry_forward" x-cloak class="grid gap-4 sm:grid-cols-2">
                <x-form.input type="number" step="0.5" wire:model="form.carry_forward_limit" :label="__('At most (days)')" :hint="__('Empty = all.')" />
                <x-form.input type="number" wire:model="form.carry_forward_expiry_months" :label="__('Expire after (months)')" :hint="__('Empty = never.')" />
            </div>
        </div>
        <div class="space-y-3">
            <x-form.toggle wire:model="form.is_encashable" :label="__('Encashable')" :hint="__('Unused days can be paid out.')" />
            <div x-show="$wire.form.is_encashable" x-cloak>
                <x-form.input type="number" step="0.5" wire:model="form.encash_limit" :label="__('At most per encashment (days)')" :hint="__('Empty = all.')" />
            </div>
        </div>
    </div>

    <flux:separator />
    <x-form.toggle wire:model="form.is_active" :label="__('Active')" />
</x-form.lookup-modal>
