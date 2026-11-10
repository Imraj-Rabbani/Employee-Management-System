<x-form.lookup-modal :$modal :$record :heading="$record ? __('Edit holiday') : __('New holiday')">
    <div class="grid gap-4 sm:grid-cols-[1fr_2fr]">
        <x-form.date wire:model="form.date" :label="__('Date')" required />
        <x-form.input wire:model="form.title" :label="__('Title')" required :hint="__('e.g. Victory Day')" />
    </div>
    <div class="grid gap-4 sm:grid-cols-3">
        <x-form.toggle wire:model="form.is_public" :label="__('Public')" />
        <x-form.toggle wire:model="form.is_festival" :label="__('Festival')" />
        <x-form.toggle wire:model="form.repeats_yearly" :label="__('Every year')" :hint="__('Same day and month')" />
    </div>

    <x-form.field :label="__('In holiday calendars')" name="profileIds"
        :hint="$profiles->isEmpty() ? __('There are no holiday calendars yet: add one on the Holiday calendars tab.') : __('Only employees on these calendars get the day off.')">
        <div class="grid gap-2 sm:grid-cols-2">
            @foreach ($profiles as $profile)
                <flux:checkbox wire:model="profileIds" value="{{ $profile->id }}"
                    :label="$profile->name.($profile->is_active ? '' : ' ('.__('inactive').')')" />
            @endforeach
        </div>
    </x-form.field>

    <x-form.textarea wire:model="form.detail" :label="__('Detail')" rows="2" />
</x-form.lookup-modal>
