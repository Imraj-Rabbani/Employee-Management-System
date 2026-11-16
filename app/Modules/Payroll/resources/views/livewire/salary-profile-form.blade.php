{{--
    New / Edit salary profile (SalaryProfileForm): code, name, grade, the value of each head ($items),
    and the "Apply profile" preview: a basic or gross → every head's amount ($breakdown).
--}}
<x-form.lookup-modal :$modal :$record size="lg" :heading="$record ? __('Edit salary profile') : __('New salary profile')"
    :deleteMessage="__('Not possible while employees use it. Make it inactive instead.')">
    <div class="grid gap-4 sm:grid-cols-3">
        <x-form.input wire:model="form.code" :label="__('Code')" required />
        <x-form.input wire:model="form.name" :label="__('Name')" required class="sm:col-span-2" :hint="__('e.g. Grade 5 staff, Workers')" />
    </div>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-form.select wire:model="form.grade_id" :label="__('Grade')" :options="$grades" :placeholder="__('Any grade')" />
        <x-form.textarea wire:model="form.detail" :label="__('Detail')" rows="1" class="sm:col-span-2" />
    </div>

    <flux:separator :text="__('Heads')" />
    @if ($basicHead)
        <flux:text class="text-sm">
            {{ __(':name is the base: its amount is set per employee. Empty value = the head is not in this profile.', ['name' => $basicHead->name]) }}
        </flux:text>
    @endif
    @error('items')
        <flux:text class="text-sm !text-red-600">{{ $message }}</flux:text>
    @enderror

    @if ($heads->isEmpty())
        <flux:text class="text-sm">{{ __('Add salary heads first (Salary heads tab). Formula and per-attendance-day heads are worked out by the payroll run, so they are not in profiles.') }}</flux:text>
    @else
        @php $amounts = $breakdown?->amounts() ?? []; @endphp
        <div class="divide-y divide-zinc-200 dark:divide-zinc-700">
            @foreach ($heads as $head)
                <div wire:key="profile-head-{{ $head->id }}" class="grid grid-cols-12 items-center gap-3 py-2">
                    <div class="col-span-12 min-w-0 sm:col-span-5">
                        <div class="text-sm font-medium">{{ $head->name }} <span class="text-zinc-500">({{ $head->code }})</span></div>
                        <div class="text-xs text-zinc-500">
                            {{ $head->type->label() }} · {{ __('own rule: :rule', ['rule' => $head->rule()]) }}
                            @unless ($head->is_active) · {{ __('Inactive') }} @endunless
                        </div>
                    </div>
                    <div class="col-span-5 sm:col-span-3">
                        <flux:select size="sm" wire:model.live="items.{{ $head->id }}.value_type" :aria-label="__('Value type')">
                            @foreach ($valueTypes as $value => $label)
                                <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    </div>
                    <div class="col-span-4 sm:col-span-2">
                        <flux:input type="number" step="0.01" min="0" size="sm" wire:model.live.debounce.400ms="items.{{ $head->id }}.value" :aria-label="$head->name" />
                    </div>
                    <div class="col-span-3 text-right text-sm tabular-nums sm:col-span-2">
                        {{ isset($amounts[$head->id]) ? $amounts[$head->id]->format() : '' }}
                    </div>
                </div>
                @error('items.'.$head->id.'.value')
                    <flux:text class="text-sm !text-red-600">{{ $message }}</flux:text>
                @enderror
            @endforeach
        </div>
    @endif

    {{-- Apply profile: what the heads come to for a basic or a gross. Not saved. --}}
    <div class="space-y-3 rounded-lg border border-zinc-200 p-4 dark:border-zinc-700">
        <flux:heading size="sm">{{ __('Apply profile') }}</flux:heading>
        <div class="grid gap-3 sm:grid-cols-2">
            <x-form.select wire:model.live="previewBasis" :label="__('Start from')" :options="$bases" />
            <x-form.input wire:model.live.debounce.400ms="previewAmount" type="number" min="0" step="0.01" :label="__('Amount')" />
        </div>

        @if ($problem)
            <flux:text class="text-sm !text-amber-600">{{ $problem }}</flux:text>
        @elseif ($breakdown)
            <table class="w-full text-sm" data-test="breakdown">
                <tbody class="divide-y divide-zinc-200 dark:divide-zinc-700">
                    @foreach ($breakdown->lines as $line)
                        <tr>
                            <td class="py-1">{{ $line->head->name }}</td>
                            <td class="py-1 text-zinc-500">
                                {{ $line->basis }}
                                @if ($line->head->type === \App\Modules\Payroll\Enums\HeadType::Deduction) · {{ __('deduction') }}
                                @elseif (! $line->inGross) · {{ __('not in gross') }} @endif
                            </td>
                            <td class="py-1 text-right tabular-nums">{{ $line->amount->format() }}</td>
                        </tr>
                    @endforeach
                </tbody>
                <tfoot class="font-medium">
                    <tr><td class="pt-2" colspan="2">{{ __('Gross') }}</td><td class="pt-2 text-right tabular-nums" data-test="gross">{{ $breakdown->gross->format() }}</td></tr>
                    <tr><td colspan="2">{{ __('Deductions') }}</td><td class="text-right tabular-nums">{{ $breakdown->deductions->format() }}</td></tr>
                    <tr><td colspan="2">{{ __('Net') }}</td><td class="text-right tabular-nums" data-test="net">{{ $breakdown->net->format() }}</td></tr>
                </tfoot>
            </table>
        @endif
    </div>

    <x-form.toggle wire:model="form.is_active" :label="__('Active')" />
</x-form.lookup-modal>
