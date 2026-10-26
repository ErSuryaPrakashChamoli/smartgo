@props([
    'categories' => [],
    'properties' => [],
    'navigation' => null,
])

@if ($navigation !== null)
    @php
        $columns = $navigation['footer'];
        $gridColumns = match (count($columns)) {
            0 => 'lg:grid-cols-1',
            1 => 'lg:grid-cols-[1.2fr_repeat(1,1fr)]',
            2 => 'lg:grid-cols-[1.2fr_repeat(2,1fr)]',
            3 => 'lg:grid-cols-[1.2fr_repeat(3,1fr)]',
            default => 'lg:grid-cols-[1.2fr_repeat(4,1fr)]',
        };
    @endphp

    {{-- The published footer columns and legal links; nothing is shown for a menu that is not published. --}}
    <footer {{ $attributes->class(['border-t border-line bg-surface']) }}>
        <div @class(['container-page grid gap-10 py-14', $gridColumns])>
            <div class="flex flex-col gap-4">
                <x-site.brand-logo size="lg" class="self-start" />
                <p class="max-w-xs text-sm text-muted">Coworking and managed workspaces in Noida, Delhi NCR.</p>
            </div>
            @foreach ($columns as $column)
                <nav aria-label="{{ $column['label'] }}" class="flex flex-col gap-3">
                    <h2 class="text-sm font-semibold text-forest">{{ $column['label'] }}</h2>
                    <ul class="flex flex-col gap-2 text-sm">
                        @foreach ($column['children'] as $link)
                            <li><a href="{{ $link['href'] }}" @if ($link['external']) rel="noopener" @endif class="text-muted hover:text-leaf-700">{{ $link['label'] }}</a></li>
                        @endforeach
                    </ul>
                </nav>
            @endforeach
        </div>
        <div class="border-t border-line">
            <div class="container-page flex flex-col gap-3 py-6 text-sm text-muted sm:flex-row sm:items-center sm:justify-between">
                <p>&copy; {{ now()->year }} SmartGro</p>
                @if ($navigation['legal'] !== [])
                    <nav aria-label="Legal">
                        <ul class="flex flex-wrap gap-x-5 gap-y-2">
                            @foreach ($navigation['legal'] as $link)
                                <li><a href="{{ $link['href'] }}" @if ($link['external']) rel="noopener" @endif class="hover:text-leaf-700">{{ $link['label'] }}</a></li>
                            @endforeach
                        </ul>
                    </nav>
                @endif
            </div>
        </div>
    </footer>
@else

{{-- Light footer: the logo has a dark wordmark, and no reversed version exists yet (D-11b). --}}
<footer {{ $attributes->class(['border-t border-line bg-surface']) }}>
    <div class="container-page grid gap-10 py-14 lg:grid-cols-[1.2fr_repeat(3,1fr)]">
        <div class="flex flex-col gap-4">
            <x-site.brand-logo size="lg" class="self-start" />
            <p class="max-w-xs text-sm text-muted">Coworking and managed workspaces in Noida, Delhi NCR.</p>
        </div>
        <nav aria-label="Workspaces" class="flex flex-col gap-3">
            <p class="text-sm font-semibold text-forest">Workspaces</p>
            <ul class="flex flex-col gap-2 text-sm">
                @foreach ($categories as $category)
                    <li><a href="#" class="text-muted hover:text-leaf-700">{{ $category['name'] }}</a></li>
                @endforeach
            </ul>
        </nav>
        <nav aria-label="Locations" class="flex flex-col gap-3">
            <p class="text-sm font-semibold text-forest">Locations in Noida</p>
            <ul class="flex flex-col gap-2 text-sm">
                @foreach ($properties as $property)
                    <li><a href="#" class="text-muted hover:text-leaf-700">{{ $property['name'] }}, {{ $property['area'] }}</a></li>
                @endforeach
            </ul>
        </nav>
        <nav aria-label="Company" class="flex flex-col gap-3">
            <p class="text-sm font-semibold text-forest">Company</p>
            <ul class="flex flex-col gap-2 text-sm">
                @foreach (['Enterprise', 'Insights', 'About', 'Contact', 'FAQs'] as $item)
                    <li><a href="#" class="text-muted hover:text-leaf-700">{{ $item }}</a></li>
                @endforeach
            </ul>
        </nav>
    </div>
    <div class="border-t border-line">
        <div class="container-page flex flex-col gap-3 py-6 text-sm text-muted sm:flex-row sm:items-center sm:justify-between">
            <p>&copy; {{ now()->year }} SmartGro</p>
            <ul class="flex flex-wrap gap-x-5 gap-y-2">
                @foreach (['Privacy', 'Terms', 'Cookies'] as $item)
                    <li><a href="#" class="hover:text-leaf-700">{{ $item }}</a></li>
                @endforeach
            </ul>
        </div>
    </div>
</footer>
@endif
