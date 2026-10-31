{{-- An import's findings, row by row (INVENTORY_IMPORT_SPEC §3 "Report"). Values are masked and truncated. --}}
@if ($issues->isEmpty())
    <p class="text-sm text-gray-700">No findings.</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">Findings by sheet, row and column</caption>
            <thead class="text-xs font-semibold text-gray-700">
                <tr>
                    <th scope="col" class="py-2 pe-3">Severity</th>
                    <th scope="col" class="py-2 pe-3">Sheet</th>
                    <th scope="col" class="py-2 pe-3">Row</th>
                    <th scope="col" class="py-2 pe-3">Column</th>
                    <th scope="col" class="py-2 pe-3">Rule</th>
                    <th scope="col" class="py-2 pe-3">What to fix</th>
                    <th scope="col" class="py-2">Value found</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-200">
                @foreach ($issues as $issue)
                    <tr>
                        <td class="py-2 pe-3 font-semibold {{ $issue->severity === 'error' ? 'text-red-700' : ($issue->severity === 'warning' ? 'text-amber-800' : 'text-gray-700') }}">{{ ucfirst($issue->severity) }}</td>
                        <td class="py-2 pe-3 text-gray-950">{{ $issue->sheet }}</td>
                        <td class="py-2 pe-3 text-gray-950">{{ $issue->row ?? '—' }}</td>
                        <td class="py-2 pe-3 text-gray-950">{{ $issue->column ?? '—' }}</td>
                        <td class="py-2 pe-3 text-gray-950">{{ $issue->rule }}</td>
                        <td class="py-2 pe-3 text-gray-950">{{ $issue->message }}</td>
                        <td class="py-2 break-all text-gray-700">{{ $issue->value_excerpt ?? '—' }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
