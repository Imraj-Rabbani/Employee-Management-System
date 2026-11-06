{{--
    Simple tabs (Alpine). The buttons switch the "tab" variable; show panes with x-show.

    <x-ui.tabs :tabs="['details' => __('Details'), 'history' => __('History')]">
        <div x-show="tab === 'details'">...</div>
        <div x-show="tab === 'history'" x-cloak>...</div>
    </x-ui.tabs>

    The first tab is open at the start.
--}}
@props([
    'tabs' => [],
])

<div x-data="{ tab: @js(array_key_first($tabs)) }" {{ $attributes }}>
    <div class="mb-4 flex gap-1 border-b border-zinc-200 dark:border-white/10" role="tablist">
        @foreach ($tabs as $key => $label)
            <button type="button" role="tab"
                x-on:click="tab = @js($key)"
                x-bind:aria-selected="tab === @js($key)"
                x-bind:class="tab === @js($key)
                    ? 'border-brand-800 text-brand-900 dark:border-white dark:text-white'
                    : 'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200'"
                class="-mb-px border-b-2 px-3 py-2 text-sm font-medium transition">
                {{ $label }}
            </button>
        @endforeach
    </div>

    {{ $slot }}
</div>
