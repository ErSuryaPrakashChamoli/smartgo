{{-- Audit entries for one version (log "content"): who did what, and when. --}}
@if ($entries->isEmpty())
    <p class="text-sm text-gray-500">No activity recorded.</p>
@else
    <ol class="flex flex-col gap-3">
        @foreach ($entries as $entry)
            <li class="flex flex-col gap-0.5 border-l-2 border-gray-200 pl-3">
                <span class="text-sm font-medium text-gray-950">{{ $entry->description }}</span>
                <span class="text-sm text-gray-600">
                    {{ $entry->causer?->name ?? 'System' }} · {{ $entry->created_at->copy()->timezone($timezone)->format('j M Y, H:i T') }}
                </span>
                @foreach (['note' => 'Note', 'reason' => 'Reason', 'restored_from' => 'Restored from version', 'based_on' => 'Based on version', 'scheduled_for' => 'Scheduled for'] as $property => $label)
                    @if (filled($entry->properties->get($property)))
                        <span class="text-sm text-gray-600">{{ $label }}: {{ $entry->properties->get($property) }}</span>
                    @endif
                @endforeach
            </li>
        @endforeach
    </ol>
@endif
