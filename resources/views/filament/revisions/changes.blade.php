{{-- What a version changes compared with the live one (RevisionChanges; UJ-8 "diff/preview"). Read-only. --}}
@if ($changes === [])
    <p class="text-sm text-gray-600">No differences from the live version.</p>
@else
    <ul class="flex flex-col divide-y divide-gray-200" aria-label="Changes">
        @foreach ($changes as $change)
            <li class="flex flex-col gap-2 py-3">
                <p class="text-sm font-semibold text-gray-950">{{ $change['field'] }}</p>
                <dl class="grid gap-2 text-sm sm:grid-cols-2 sm:gap-4">
                    <div class="flex flex-col gap-0.5">
                        <dt class="text-xs font-semibold text-gray-600">Live</dt>
                        <dd class="break-words text-gray-700">{{ $change['before'] }}</dd>
                    </div>
                    <div class="flex flex-col gap-0.5">
                        <dt class="text-xs font-semibold text-gray-600">This version</dt>
                        <dd class="break-words text-gray-950">{{ $change['after'] }}</dd>
                    </div>
                </dl>
            </li>
        @endforeach
    </ul>
@endif
