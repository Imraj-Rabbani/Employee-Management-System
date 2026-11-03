{{--
    Toast notifications. Placed once in the app layout.

    From a Livewire component:
        $this->dispatch('toast', message: 'Employee saved', variant: 'success');

    From a controller (after a redirect):
        return redirect()->route('...')->with('toast', ['message' => 'Employee saved', 'variant' => 'success']);

    Variants: success, error, warning, info.
--}}
<div
    x-data="{
        toasts: [],
        add(detail) {
            const id = Date.now() + Math.random();
            this.toasts.push({ id, message: detail.message, variant: detail.variant ?? 'success' });
            setTimeout(() => this.remove(id), detail.duration ?? 4000);
        },
        remove(id) {
            this.toasts = this.toasts.filter((toast) => toast.id !== id);
        },
    }"
    @if (session('toast')) x-init="add(@js(session('toast')))" @endif
    x-on:toast.window="add($event.detail)"
    role="status"
    aria-live="polite"
    class="pointer-events-none fixed top-20 right-4 z-50 flex w-full max-w-sm flex-col gap-2"
>
    <template x-for="toast in toasts" :key="toast.id">
        <div
            x-transition:enter="transition duration-300 ease-out"
            x-transition:enter-start="-translate-y-2 opacity-0"
            x-transition:enter-end="translate-y-0 opacity-100"
            class="glass pointer-events-auto flex items-start gap-3 rounded-2xl p-4 text-sm text-zinc-800 dark:text-white"
        >
            <flux:icon.check-circle variant="mini" class="shrink-0 text-green-600 dark:text-green-400" x-show="toast.variant === 'success'" />
            <flux:icon.x-circle variant="mini" class="shrink-0 text-red-600 dark:text-red-400" x-show="toast.variant === 'error'" />
            <flux:icon.exclamation-triangle variant="mini" class="shrink-0 text-amber-500 dark:text-amber-400" x-show="toast.variant === 'warning'" />
            <flux:icon.information-circle variant="mini" class="shrink-0 text-brand-600 dark:text-brand-300" x-show="toast.variant === 'info'" />

            <p class="flex-1" x-text="toast.message"></p>

            <button type="button" x-on:click="remove(toast.id)" class="shrink-0 text-zinc-400 hover:text-zinc-700 dark:hover:text-white" aria-label="{{ __('Close') }}">
                <flux:icon.x-mark variant="mini" />
            </button>
        </div>
    </template>
</div>
