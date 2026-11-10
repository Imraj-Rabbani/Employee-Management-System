{{--
    Attachment input for an EmployeeRecords form: the current file (download link + "Remove"),
    and a file input to add / replace it. The component must have attachmentFolder() set.

    <x-form.attachment :$record :url="$record ? route('hrm.employees.attachment', [$employee, $section, $record->id]) : null" />
--}}
@props([
    'record' => null,
    'url' => null,
    'label' => null,
])

<div class="space-y-2">
    @if ($record?->attachment_path)
        <div class="flex items-center gap-2 text-sm">
            <flux:icon.paper-clip variant="micro" class="text-zinc-500" />
            <a href="{{ $url }}" class="font-medium text-brand-800 underline-offset-4 hover:underline dark:text-brand-200">{{ $record->attachment_name ?? __('Attachment') }}</a>
            <flux:button size="xs" variant="ghost" class="!text-red-600" x-on:click="$dispatch('confirm', {
                title: @js(__('Remove this file?')),
                confirmText: @js(__('Remove')),
                onConfirm: () => $wire.removeAttachment(),
            })">{{ __('Remove') }}</flux:button>
        </div>
    @endif

    <x-form.file wire:model="upload" :label="$label ?? ($record?->attachment_path ? __('Replace file') : __('Attachment'))"
        accept=".pdf,.jpg,.jpeg,.png,.docx" :hint="__('PDF, JPG, PNG or Word, max 5 MB.')" />
</div>
