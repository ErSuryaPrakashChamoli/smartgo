{{-- A report breakdown (EnquiryReport): $caption for screen readers, $heading for the first column, $rows of ['label' => string, 'total' => int], $empty when there is nothing. Counts only. --}}
@if ($rows === [])
    <p class="text-sm text-gray-700 dark:text-gray-200">{{ $empty }}</p>
@else
    <div class="overflow-x-auto">
        <table class="w-full text-left text-sm">
            <caption class="sr-only">{{ $caption }}</caption>
            <thead>
                <tr class="border-b border-gray-200 dark:border-white/10">
                    <th scope="col" class="py-2 pr-4 font-semibold text-gray-950 dark:text-white">{{ $heading }}</th>
                    <th scope="col" class="py-2 pr-4 font-semibold text-gray-950 dark:text-white">Number</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-gray-100 dark:divide-white/5">
                @foreach ($rows as $row)
                    <tr>
                        <td class="py-2.5 pr-4 text-gray-700 dark:text-gray-200">{{ $row['label'] }}</td>
                        <td class="py-2.5 pr-4 text-gray-700 dark:text-gray-200">{{ number_format($row['total']) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endif
