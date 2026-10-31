{{-- A price plan's or offer's audited history (RecordPricingActivity), newest first. Read-only. --}}
@php($timezone = config('backoffice.timezone'))
@if ($entries->isEmpty())
    <p class="text-sm text-gray-700">No history yet.</p>
@else
    <ol class="flex flex-col divide-y divide-gray-200" aria-label="History">
        @foreach ($entries as $entry)
            <li class="flex flex-col gap-0.5 py-2 text-sm">
                <p class="font-semibold text-gray-950">{{ $entry->description }}</p>
                <p class="text-gray-700">{{ $entry->causer?->name ?? 'System' }}, {{ $entry->created_at->copy()->timezone($timezone)->format('j M Y, H:i T') }}</p>
                @if (filled($entry->properties['reason'] ?? null))
                    <p class="text-gray-700">Reason: {{ $entry->properties['reason'] }}</p>
                @endif
            </li>
        @endforeach
    </ol>
@endif
