{{-- Variables: $columns (list of headings), $rows (list of lists of cell text), $caption (screen-reader caption). --}}

<div class="overflow-x-auto">
    <table class="w-full min-w-[36rem] text-left text-sm">
        <caption class="sr-only">{{ $caption }}</caption>
        <thead>
            <tr class="border-b border-gray-200 dark:border-white/10">
                @foreach ($columns as $column)
                    <th scope="col" class="py-2 pr-4 font-semibold text-gray-950 dark:text-white">{{ $column }}</th>
                @endforeach
            </tr>
        </thead>
        <tbody class="divide-y divide-gray-100 dark:divide-white/5">
            @foreach ($rows as $row)
                <tr>
                    @foreach ($row as $cell)
                        <td class="py-2.5 pr-4 text-gray-700 dark:text-gray-200">{{ $cell }}</td>
                    @endforeach
                </tr>
            @endforeach
        </tbody>
    </table>
</div>
