<div class="space-y-4">
    @if (! $employee)
        <x-ui.card>
            <x-ui.empty icon="user" :heading="__('Your login is not linked to an employee record')"
                :description="__('Ask HR to link it (Employees → profile → Login).')" />
        </x-ui.card>
    @else
        <div class="flex items-center justify-between gap-4">
            <flux:subheading>{{ __('Changes to your personal details are applied after approval.') }}</flux:subheading>
            <flux:button variant="primary" icon="pencil-square" wire:click="open">{{ __('Request a change') }}</flux:button>
        </div>

        <x-ui.card :padding="false">
            @if ($requests->isEmpty())
                <x-ui.empty icon="document-text" :heading="__('No requests yet')" />
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
        </x-ui.card>

        <x-ui.modal name="profile-request-form" size="lg" :heading="__('Request a change')"
            :subheading="__('Change only what is wrong. HR sees your old and new values side by side.')">
            <form wire:submit="submit" id="profile-request-form" class="space-y-6">
                <div class="grid gap-4 sm:grid-cols-[1fr_2fr_2fr]">
                    <x-form.input wire:model="form.title" :label="__('Title')" />
                    <x-form.input wire:model="form.first_name" :label="__('First name')" required />
                    <x-form.input wire:model="form.last_name" :label="__('Last name')" />
                </div>
                <x-form.input wire:model="form.name_local" :label="__('Name (local)')" />
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-form.select wire:model="form.gender" :label="__('Gender')" :options="$genders" :placeholder="__('Not set')" />
                    <x-form.date wire:model="form.birth_date" :label="__('Date of birth')" />
                    <x-form.select wire:model="form.marital_status" :label="__('Marital status')" :options="$maritalStatuses" :placeholder="__('Not set')" />
                    <x-form.select wire:model="form.blood_group" :label="__('Blood group')" :options="$bloodGroups" :placeholder="__('Not set')" />
                    <x-form.select wire:model="form.religion" :label="__('Religion')" :options="$religions" :placeholder="__('Not set')" />
                    <x-form.input wire:model="form.nationality" :label="__('Nationality')" />
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <x-form.input wire:model="form.national_id" :label="__('National ID')" />
                    <x-form.input wire:model="form.tin" :label="__('TIN')" />
                    <x-form.input wire:model="form.passport_no" :label="__('Passport no.')" />
                    <x-form.input wire:model="form.mobile" :label="__('Mobile')" type="tel" />
                    <x-form.input wire:model="form.personal_email" :label="__('Personal email')" type="email" />
                </div>
                <x-form.textarea wire:model="reason" :label="__('Reason')" rows="2" :hint="__('e.g. new phone number, name changed after marriage')" />
            </form>

            <x-slot:footer>
                <flux:button type="submit" form="profile-request-form" variant="primary">{{ __('Send for approval') }}</flux:button>
            </x-slot:footer>
        </x-ui.modal>
    @endif
</div>
