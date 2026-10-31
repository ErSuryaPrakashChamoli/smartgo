{{-- The preview of an inventory import (InventoryImporter::plan): counts per sheet, effect on availability, and each change. --}}
<div class="flex flex-col gap-4 text-sm">
    @if (filled($summary['already_imported'] ?? null))
        <p class="font-semibold text-amber-800">{{ $summary['already_imported'] }}</p>
    @endif
    <div class="overflow-x-auto">
        <table class="w-full text-left">
            <caption class="sr-only">Records per sheet</caption>
            <thead class="text-xs font-semibold text-gray-700">
                <tr><th scope="col" class="py-1 pe-3">Sheet</th><th scope="col" class="py-1 pe-3">New</th><th scope="col" class="py-1 pe-3">Changed</th><th scope="col" class="py-1 pe-3">Unchanged</th><th scope="col" class="py-1">Not in file (no change)</th></tr>
            </thead>
            <tbody class="divide-y divide-gray-200 text-gray-950">
                @foreach ($summary['counts'] ?? [] as $sheet => $count)
                    <tr><td class="py-1 pe-3">{{ $sheet }}</td><td class="py-1 pe-3">{{ $count['new'] }}</td><td class="py-1 pe-3">{{ $count['updated'] }}</td><td class="py-1 pe-3">{{ $count['unchanged'] }}</td><td class="py-1">{{ $count['not_in_file'] }}</td></tr>
                @endforeach
            </tbody>
        </table>
    </div>
    @foreach (array_merge($summary['impact'] ?? [], $summary['notes'] ?? []) as $line)
        <p class="text-gray-950">{{ $line }}</p>
    @endforeach
    @php($updates = collect($summary['changes'] ?? [])->where('kind', 'updated'))
    @if ($updates->isNotEmpty())
        <ul class="flex flex-col gap-1" aria-label="Changes to existing records">
            @foreach ($updates->take(200) as $change)
                <li class="text-gray-950">{{ $change['sheet'] }} {{ $change['key'] }}:
                    {{ collect($change['fields'])->map(fn ($pair, $field) => $field.' '.json_encode($pair[0]).' → '.json_encode($pair[1]))->implode('; ') }}</li>
            @endforeach
        </ul>
    @endif
</div>
