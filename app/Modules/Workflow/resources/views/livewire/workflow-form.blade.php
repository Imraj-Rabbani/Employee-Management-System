<div>
    <x-ui.modal name="workflow-form" size="lg" :heading="__(':name workflow', ['name' => $typeName])">
        <form wire:submit="save" id="workflow-form" class="space-y-6">
            <div class="space-y-3">
                <div class="flex items-center justify-between">
                    <flux:heading>{{ __('Levels') }}</flux:heading>
                    <flux:button size="sm" icon="plus" wire:click="addStep">{{ __('Add level') }}</flux:button>
                </div>
                <flux:text class="text-xs">{{ __('Level 1 is asked first. When it approves, the request goes to the next level automatically. One level is enough.') }}</flux:text>
                <flux:error name="steps" />

                @foreach ($steps as $i => $step)
                    @php $type = \App\Modules\Workflow\Enums\ApproverType::tryFrom($step['approver_type']); @endphp
                    <div class="space-y-3 rounded-lg border border-zinc-200 p-3 dark:border-white/10" wire:key="step-{{ $i }}">
                        {{-- Level number and the level's buttons --}}
                        <div class="flex items-center gap-2">
                            <flux:heading size="sm">{{ __('Level :level', ['level' => $i + 1]) }}</flux:heading>
                            <flux:spacer />
                            <flux:checkbox wire:model="steps.{{ $i }}.attachment_required" :label="__('Attachment required')" />
                            @if ($i > 0)
                                <flux:button size="sm" variant="ghost" icon="arrow-up" wire:click="moveUp({{ $i }})" :aria-label="__('Move up')" />
                            @endif
                            @if (count($steps) > 1)
                                <flux:button size="sm" variant="ghost" icon="trash" wire:click="removeStep({{ $i }})" :aria-label="__('Remove level')" />
                            @endif
                        </div>

                        {{-- Type, then only the fields that type needs. .live: changing them changes the fields shown. --}}
                        <div class="grid gap-3 sm:grid-cols-3">
                            <flux:field>
                                <flux:label>{{ __('Type') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                                <flux:select aria-required="true" wire:model.live="steps.{{ $i }}.approver_type">
                                    @foreach ($approverTypes as $option)
                                        <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                                    @endforeach
                                </flux:select>
                                <flux:error name="steps.{{ $i }}.approver_type" />
                            </flux:field>

                            @if ($type?->needsEntityType())
                                <flux:field>
                                    <flux:label>{{ __('Entity type') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                                    <flux:select aria-required="true" wire:model.live="steps.{{ $i }}.approver_entity_type_id">
                                        <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                                        @foreach ($entityTypes as $optionValue => $optionLabel)
                                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:error name="steps.{{ $i }}.approver_entity_type_id" />
                                </flux:field>
                            @endif

                            @if ($type === \App\Modules\Workflow\Enums\ApproverType::SpecificEntityHead)
                                <flux:field>
                                    <flux:label>{{ __('Specific entity') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                                    <flux:select aria-required="true" wire:model="steps.{{ $i }}.approver_entity_id" :disabled="$step['approver_entity_type_id'] === ''">
                                        <flux:select.option value="">{{ $step['approver_entity_type_id'] === '' ? __('Choose the entity type first') : __('Choose…') }}</flux:select.option>
                                        @foreach ($entitiesByType[(int) $step['approver_entity_type_id']] ?? [] as $optionValue => $optionLabel)
                                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:error name="steps.{{ $i }}.approver_entity_id" />
                                </flux:field>
                            @endif

                            @if ($type === \App\Modules\Workflow\Enums\ApproverType::Employee)
                                <flux:field class="sm:col-span-2">
                                    <flux:label>{{ __('Specific employee') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                                    <flux:select aria-required="true" wire:model="steps.{{ $i }}.approver_employee_id">
                                        <flux:select.option value="">{{ __('Choose…') }}</flux:select.option>
                                        @foreach ($employees as $optionValue => $optionLabel)
                                            <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                                        @endforeach
                                    </flux:select>
                                    <flux:error name="steps.{{ $i }}.approver_employee_id" />
                                </flux:field>
                            @endif
                        </div>

                        {{-- What this level means, in words. --}}
                        <flux:text class="text-xs">
                            @switch ($type)
                                @case(\App\Modules\Workflow\Enums\ApproverType::Supervisor)
                                    {{ __('The reporting supervisor of the employee who asks.') }}
                                    @break
                                @case(\App\Modules\Workflow\Enums\ApproverType::EntityHead)
                                    {{ __('The head of the employee\'s own entity of this type, e.g. the head of their Branch.') }}
                                    @break
                                @case(\App\Modules\Workflow\Enums\ApproverType::SpecificEntityHead)
                                    {{ __('The head of this one entity, whoever asks.') }}
                                    @break
                                @case(\App\Modules\Workflow\Enums\ApproverType::Employee)
                                    {{ __('Always this person.') }}
                                    @break
                            @endswitch
                            @if ($type?->needsEntityType())
                                {{ __('When the entity has no head (or the head is the person asking), the head of its parent entity is asked.') }}
                            @endif
                        </flux:text>
                    </div>
                @endforeach
            </div>

            <flux:separator />

            <div class="grid gap-4 sm:grid-cols-2">
                <flux:switch wire:model="isActive" :label="__('Active')" />
                <flux:switch wire:model="sendEmail" :label="__('Send emails')" />
                <flux:switch wire:model="allowReview" :label="__('Approvers may send back')" />
                <flux:switch wire:model="allowReject" :label="__('Approvers may reject')" />
            </div>
            <flux:text class="text-xs">{{ __('Switched off, requests of this type cannot be sent for approval.') }}</flux:text>
        </form>

        <x-slot:footer>
            <flux:button type="submit" form="workflow-form" variant="primary">{{ __('Save') }}</flux:button>
        </x-slot:footer>
    </x-ui.modal>
</div>
