@props(['data'])

{{-- Scrolls sideways inside its own box on narrow screens, so the page itself never does. --}}
<section class="container-page py-10 sm:py-14">
    <div class="flex flex-col gap-6">
        <div class="flex max-w-3xl flex-col gap-3">
            <h2 class="font-display text-display-lg font-semibold text-balance text-forest">{{ $data['heading'] }}</h2>
            @if (filled($data['intro']))
                <p class="text-lead text-pretty text-muted">{{ $data['intro'] }}</p>
            @endif
        </div>
        <div class="overflow-x-auto rounded-2xl bg-surface shadow-card ring-1 ring-line" tabindex="0" role="region" aria-label="{{ $data['heading'] }}">
            <table class="w-full min-w-[32rem] text-left text-sm">
                <caption class="sr-only">{{ $data['heading'] }}</caption>
                <thead>
                    <tr class="border-b border-line">
                        <td class="p-4"></td>
                        @foreach ($data['columns'] as $column)
                            <th scope="col" class="p-4 font-display text-base font-semibold text-forest">{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($data['rows'] as $row)
                        <tr class="border-b border-line last:border-0">
                            <th scope="row" class="p-4 font-semibold text-ink">{{ $row['label'] }}</th>
                            @foreach ($row['cells'] as $cell)
                                <td class="p-4 text-muted">{{ $cell }}</td>
                            @endforeach
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
</section>
