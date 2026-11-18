<div>
    <x-ui.modal name="workflow-form" size="lg" :heading="$workflowId ? __('Edit workflow') : __('New workflow')">
        <form wire:submit="save" id="workflow-form" class="space-y-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:field>
                    <flux:label>{{ __('Type') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:select aria-required="true" wire:model="approvalTypeId">
                        <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                        @foreach ($types as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="approvalTypeId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                    <flux:input :placeholder="__('e.g. Leave, head office')" aria-required="true" wire:model="name" />
                    <flux:error name="name" />
                </flux:field>
            </div>

            <div class="grid gap-4 sm:grid-cols-3">
                <flux:field>
                    <flux:label>{{ __('Only for entity') }}</flux:label>
                    <flux:select wire:model="scopeEntityId">
                        <flux:select.option value="">{{ __('Any entity') }}</flux:select.option>
                        @foreach ($entities as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="scopeEntityId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Only for employment type') }}</flux:label>
                    <flux:select wire:model="scopeEmploymentTypeId">
                        <flux:select.option value="">{{ __('Any type') }}</flux:select.option>
                        @foreach ($employmentTypes as $optionValue => $optionLabel)
                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:error name="scopeEmploymentTypeId" />
                </flux:field>

                <flux:field>
                    <flux:label>{{ __('Only from this amount') }}</flux:label>
                    <flux:input wire:model="minAmount" type="number" step="0.01" min="0" />
                    <flux:error name="minAmount" />
                </flux:field>
            </div>
            <flux:text class="text-xs">{{ __('Leave empty to apply to everything. When several workflows match, the one with the most filled fields wins.') }}</flux:text>

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:switch wire:model="sendEmail" :label="__('Send emails')" />

                <flux:switch wire:model="isActive" :label="__('Active')" />
                <flux:switch wire:model="allowReview" :label="__('Approvers may send back')" />

                <flux:switch wire:model="allowReject" :label="__('Approvers may reject')" />
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

                        <flux:field>
                            <flux:label>{{ __('Approver') }}</flux:label>
                            <flux:select wire:model.live="steps.{{ $i }}.approver_type">
                                @foreach ($approverTypes as $type)
                                    @php $isOffered = in_array($type->value, $offered, true); @endphp
                                    <flux:select.option :value="$type->value" :disabled="! $isOffered">
                                        {{ $type->label() }}@if (! $isOffered) ({{ __('soon') }})@endif
                                    </flux:select.option>
                                @endforeach
                            </flux:select>
                            <flux:error name="steps.{{ $i }}.approver_type" />
                        </flux:field>

                        {{-- The second field depends on the approver type. --}}
                        @if ($step['approver_type'] === 'entity_head')
                            <flux:field>
                                <flux:label>{{ __('Head of which type') }}</flux:label>
                                <flux:select wire:model="steps.{{ $i }}.approver_entity_type_id">
                                    <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                                    @foreach ($entityTypes as $optionValue => $optionLabel)
                                        <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="steps.{{ $i }}.approver_entity_type_id" />
                            </flux:field>
                        @else
                            <flux:field>
                                <flux:label>{{ __('Role') }}</flux:label>
                                <flux:select wire:model="steps.{{ $i }}.approver_role_id">
                                    <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                                    @foreach ($roles as $optionValue => $optionLabel)
                                        <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="steps.{{ $i }}.approver_role_id" />
                            </flux:field>
                        @endif

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
