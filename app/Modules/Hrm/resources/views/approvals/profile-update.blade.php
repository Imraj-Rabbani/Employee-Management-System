{{-- Shown to the approver (approval modal + email page): what the employee wants to change. $document = ProfileUpdateRequest --}}
@php /** @var \App\Modules\Hrm\Models\ProfileUpdateRequest $document */ @endphp
<div class="space-y-2">
    <div class="overflow-hidden rounded-xl ring-1 ring-zinc-500/15">
        <table class="w-full text-sm">
            <thead class="bg-zinc-500/5 text-left text-xs uppercase tracking-wide text-zinc-500">
                <tr>
                    <th class="px-4 py-2 font-medium">{{ __('Field') }}</th>
                    <th class="px-4 py-2 font-medium">{{ __('Now') }}</th>
                    <th class="px-4 py-2 font-medium">{{ __('Requested') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($document->field_changes as $field => $change)
                    <tr class="border-t border-zinc-500/10">
                        <td class="px-4 py-2 font-medium">{{ $document::label($field) }}</td>
                        <td class="px-4 py-2 text-zinc-500 line-through decoration-zinc-400/60">{{ $document::display($field, $change['old']) }}</td>
                        <td class="px-4 py-2 font-medium text-green-700 dark:text-green-300">{{ $document::display($field, $change['new']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @if ($document->reason)
        <flux:text class="text-sm"><span class="font-medium">{{ __('Reason') }}:</span> {{ $document->reason }}</flux:text>
    @endif
</div>
