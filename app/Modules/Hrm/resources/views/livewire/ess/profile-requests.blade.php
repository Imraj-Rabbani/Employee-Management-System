<div class="space-y-4">
    @if (! $employee)
        <div class="glass overflow-hidden rounded-2xl">
            <div class="p-6">
                <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
                    <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                        <flux:icon name="user" />
                    </div>
                    <flux:heading size="lg" class="mt-4">{{ __('Your login is not linked to an employee record') }}</flux:heading>
                    <flux:text class="mt-1 max-w-sm">{{ __('Ask HR to link it (Employees → profile → Login).') }}</flux:text>
                </div>
            </div>
        </div>
    @else
        <div class="flex items-center justify-between gap-4">
            <flux:subheading>{{ __('Changes to your personal details are applied after approval.') }}</flux:subheading>
            <flux:button variant="primary" icon="pencil-square" wire:click="open">{{ __('Request a change') }}</flux:button>
        </div>

        <div class="glass overflow-hidden rounded-2xl">
            @if ($requests->isEmpty())
                <div class="flex flex-col items-center justify-center px-6 py-12 text-center">
                    <div class="flex size-12 items-center justify-center rounded-2xl bg-brand-800/10 text-brand-800 dark:bg-white/10 dark:text-brand-200">
                        <flux:icon name="document-text" />
                    </div>
                    <flux:heading size="lg" class="mt-4">{{ __('No requests yet') }}</flux:heading>
                </div>
            @else
                <ul class="divide-y divide-white/50 dark:divide-white/10">
                    @foreach ($requests as $request)
                        @php
                            $status = $request->approvalStatus();
                            $sentBack = $request->approvals->where('status', \App\Modules\Workflow\Enums\ApprovalStatus::Review)->sortByDesc('responded_at')->first()
                                ?? $request->approvals->where('status', \App\Modules\Workflow\Enums\ApprovalStatus::Rejected)->sortByDesc('responded_at')->first();
                        @endphp
                        <li class="space-y-3 px-6 py-4" wire:key="request-{{ $request->id }}">
                            <div class="flex flex-wrap items-center gap-2">
                                @if ($status)
                                    <x-ui.badge :color="$status->color()">{{ $request->applied_at ? __('Applied') : $status->label() }}</x-ui.badge>
                                @endif
                                <span class="text-sm text-zinc-500">{{ format_datetime($request->created_at) }}</span>
                            </div>
                            @include('hrm::approvals.profile-update', ['document' => $request])
                            @if ($sentBack?->comment)
                                <flux:callout icon="chat-bubble-left-ellipsis" variant="warning" :heading="__('Comment from the approver')">
                                    <flux:callout.text>{{ $sentBack->comment }}</flux:callout.text>
                                </flux:callout>
                            @endif
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        <x-ui.modal name="profile-request-form" size="lg" :heading="__('Request a change')"
            :subheading="__('Change only what is wrong. HR sees your old and new values side by side.')">
            <form wire:submit="submit" id="profile-request-form" class="space-y-6">
                <div class="grid gap-4 sm:grid-cols-[1fr_2fr_2fr]">
                    <flux:field>
                        <flux:label>{{ __('Title') }}</flux:label>
                        <flux:input wire:model="form.title" />
                        <flux:error name="form.title" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('First name') }} <span class="ms-0.5 text-red-500 dark:text-red-400" aria-hidden="true">*</span></flux:label>
                        <flux:input aria-required="true" wire:model="form.first_name" />
                        <flux:error name="form.first_name" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Last name') }}</flux:label>
                        <flux:input wire:model="form.last_name" />
                        <flux:error name="form.last_name" />
                    </flux:field>
                </div>
                <flux:field>
                    <flux:label>{{ __('Name (local)') }}</flux:label>
                    <flux:input wire:model="form.name_local" />
                    <flux:error name="form.name_local" />
                </flux:field>
                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:field>
                        <flux:label>{{ __('Gender') }}</flux:label>
                        <flux:select wire:model="form.gender">
                            <flux:select.option value="">{{ __('Not set') }}</flux:select.option>
                            @foreach ($genders as $optionValue => $optionLabel)
                                <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="form.gender" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Date of birth') }}</flux:label>
                        <flux:input type="date" wire:model="form.birth_date" />
                        <flux:error name="form.birth_date" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Marital status') }}</flux:label>
                        <flux:select wire:model="form.marital_status">
                            <flux:select.option value="">{{ __('Not set') }}</flux:select.option>
                            @foreach ($maritalStatuses as $optionValue => $optionLabel)
                                <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="form.marital_status" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Blood group') }}</flux:label>
                        <flux:select wire:model="form.blood_group">
                            <flux:select.option value="">{{ __('Not set') }}</flux:select.option>
                            @foreach ($bloodGroups as $optionValue => $optionLabel)
                                <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="form.blood_group" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Religion') }}</flux:label>
                        <flux:select wire:model="form.religion">
                            <flux:select.option value="">{{ __('Not set') }}</flux:select.option>
                            @foreach ($religions as $optionValue => $optionLabel)
                                <flux:select.option :value="$optionValue">{{ $optionLabel }}</flux:select.option>
                            @endforeach
                        </flux:select>
                        <flux:error name="form.religion" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Nationality') }}</flux:label>
                        <flux:input wire:model="form.nationality" />
                        <flux:error name="form.nationality" />
                    </flux:field>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:field>
                        <flux:label>{{ __('National ID') }}</flux:label>
                        <flux:input wire:model="form.national_id" />
                        <flux:error name="form.national_id" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('TIN') }}</flux:label>
                        <flux:input wire:model="form.tin" />
                        <flux:error name="form.tin" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Passport no.') }}</flux:label>
                        <flux:input wire:model="form.passport_no" />
                        <flux:error name="form.passport_no" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Mobile') }}</flux:label>
                        <flux:input wire:model="form.mobile" type="tel" />
                        <flux:error name="form.mobile" />
                    </flux:field>

                    <flux:field>
                        <flux:label>{{ __('Personal email') }}</flux:label>
                        <flux:input wire:model="form.personal_email" type="email" />
                        <flux:error name="form.personal_email" />
                    </flux:field>
                </div>
                <flux:field>
                    <flux:label>{{ __('Reason') }}</flux:label>
                    <flux:description>{{ __('e.g. new phone number, name changed after marriage') }}</flux:description>
                    <flux:textarea wire:model="reason" rows="2" />
                    <flux:error name="reason" />
                </flux:field>
            </form>

            <x-slot:footer>
                <flux:button type="submit" form="profile-request-form" variant="primary">{{ __('Send for approval') }}</flux:button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
