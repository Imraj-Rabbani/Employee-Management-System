{{--
    "Are you sure?" dialog. Placed once in the app layout.

    Open it from any button by dispatching a "confirm" event:

        <flux:button variant="danger" x-on:click="$dispatch('confirm', {
            title: 'Delete employee?',
            message: 'This cannot be undone.',
            confirmText: 'Delete',
            onConfirm: () => $wire.delete({{ $employee->id }}),
        })">Delete</flux:button>

    onConfirm only runs when the user clicks the confirm button.
--}}
<div
    x-data="{
        title: '',
        message: '',
        confirmText: '',
        onConfirm: null,
        open(detail) {
            this.title = detail.title ?? @js(__('Are you sure?'));
            this.message = detail.message ?? '';
            this.confirmText = detail.confirmText ?? @js(__('Confirm'));
            this.onConfirm = detail.onConfirm ?? null;
        },
    }"
    x-on:confirm.window="open($event.detail); $flux.modal('confirm').show()"
>
    <flux:modal name="confirm" variant="bare" class="w-full max-w-md">
        <div class="glass space-y-6 rounded-2xl p-6">
            <div class="flex items-start gap-4">
                <div class="flex size-10 shrink-0 items-center justify-center rounded-full bg-red-100 dark:bg-red-500/20">
                    <flux:icon.exclamation-triangle class="size-5 text-red-600 dark:text-red-400" />
                </div>

                <div>
                    <flux:heading size="lg" x-text="title"></flux:heading>
                    <flux:text class="mt-2" x-text="message" x-show="message"></flux:text>
                </div>
            </div>

            <div class="flex justify-end gap-2">
                <flux:modal.close>
                    <flux:button variant="ghost">{{ __('Cancel') }}</flux:button>
                </flux:modal.close>

                <flux:button variant="danger" x-on:click="onConfirm && onConfirm(); $flux.modal('confirm').close()">
                    <span x-text="confirmText"></span>
                </flux:button>
            </div>
        </div>
    </flux:modal>
</div>
