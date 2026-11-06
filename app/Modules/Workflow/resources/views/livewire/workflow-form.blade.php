<div>
    <x-ui.modal name="workflow-form" size="lg" :heading="$workflowId ? __('Edit workflow') : __('New workflow')">
        <form wire:submit="save" id="workflow-form" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.select wire:model="approvalTypeId" :label="__('Type')" :options="$types" :placeholder="__('Choose…')" required />
                <x-form.input wire:model="name" :label="__('Name')" required :hint="__('e.g. Leave, head office')" />
            </div>

            <x-form.input wire:model="minAmount" type="number" step="0.01" min="0" :label="__('Only from this amount')"
                :hint="__('Empty = every document. Filled: used for amounts from this value up, e.g. loans from 50,000.')" />

            <div class="grid gap-4 sm:grid-cols-2">
                <x-form.toggle wire:model="sendEmail" :label="__('Send emails')" />
                <x-form.toggle wire:model="isActive" :label="__('Active')" />
                <x-form.toggle wire:model="allowReview" :label="__('Approvers may send back')" />
                <x-form.toggle wire:model="allowReject" :label="__('Approvers may reject')" />
            </div>

            <flux:separator />

            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <flux:heading>{{ __('Levels') }}</flux:heading>
                    <flux:button size="sm" icon="plus" wire:click="addStep">{{ __('Add level') }}</flux:button>
                </div>
                <flux:error name="steps" />

                @foreach ($steps as $i => $step)
                    <div class="grid items-end gap-3 rounded-lg border border-zinc-200 p-3 sm:grid-cols-[3rem_1fr_1fr_auto] dark:border-white/10" wire:key="step-{{ $i }}">
                        <div class="text-sm font-medium text-zinc-500">{{ __('L:level', ['level' => $i + 1]) }}</div>

                        <x-form.select wire:model.live="steps.{{ $i }}.approver_type" :label="__('Approver')">
                            @foreach ($approverTypes as $type)
                                {{-- Employee-based approvers need employee records (Phase 5). --}}
                                <flux:select.option :value="$type->value" :disabled="$type->value !== 'role'">
                                    {{ $type->label() }}@if ($type->value !== 'role') ({{ __('soon') }})@endif
                                </flux:select.option>
                            @endforeach
                        </x-form.select>

                        <x-form.select wire:model="steps.{{ $i }}.approver_role_id" :label="__('Role')" :options="$roles" :placeholder="__('Choose…')" />

                        <div class="flex items-center gap-1">
                            <flux:tooltip :content="__('Attachment required to approve')">
                                <flux:checkbox wire:model="steps.{{ $i }}.attachment_required" :aria-label="__('Attachment required to approve')" />
                            </flux:tooltip>
                            @if ($i > 0)
                                <flux:button size="sm" variant="ghost" icon="arrow-up" wire:click="moveUp({{ $i }})" :aria-label="__('Move up')" />
                            @endif
                            <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeStep({{ $i }})" :aria-label="__('Remove level')" />
                        </div>
                    </div>
                @endforeach

                <flux:text class="text-xs">{{ __('Tick the box on a level to require an attachment when approving.') }}</flux:text>
            </div>
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="workflow-form" variant="primary">{{ __('Save') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
