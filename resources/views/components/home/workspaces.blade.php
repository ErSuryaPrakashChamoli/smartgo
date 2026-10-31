@props([
    'groups',
    'enquiryOpen' => false,
])

@php
    $columns = match (count($groups)) {
        1 => 'sm:max-w-md',
        2 => 'sm:grid-cols-2',
        3 => 'sm:grid-cols-2 lg:grid-cols-3',
        4 => 'sm:grid-cols-2 lg:grid-cols-4',
        default => 'sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5',
    };
@endphp

{{--
    Each family of workspace types as one compact card in an even row, as in the owner's reference (OD-141):
    a published photo of one of its offerings or a pictogram, the family, where each type is offered
    (published offerings only) and the next step. Never a price.
--}}
<section id="workspaces" class="scroll-mt-24 bg-mist/50 py-14 sm:py-20" aria-labelledby="workspaces-heading">
    <div class="container-page flex flex-col gap-6 sm:gap-8">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between" data-reveal>
            <div class="flex max-w-2xl flex-col gap-2">
                <h2 id="workspaces-heading" class="font-editorial text-display-lg font-semibold text-balance text-forest">Our workspaces</h2>
                <p class="text-lg text-pretty text-muted">From a single desk to a whole office: choose the kind of workspace that fits your team.</p>
            </div>
            <a href="/workspaces" class="group inline-flex shrink-0 items-center gap-1.5 self-start rounded-full px-1 text-[0.9375rem] font-semibold text-leaf-700 hover:text-leaf-800 sm:self-auto">
                Explore all workspaces
                <x-heroicon-m-arrow-right class="size-4 transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
            </a>
        </div>

        <div class="grid gap-5 {{ $columns }}" data-reveal-group>
            @foreach ($groups as $group)
                @php
                    $icon = $group['categories'][0]['icon'] ?? 'squares-2x2';
                    $single = count($group['categories']) === 1 ? $group['categories'][0] : null;
                @endphp
                <article id="{{ $group['anchor'] }}" class="group/card flex scroll-mt-24 flex-col overflow-hidden rounded-3xl bg-surface shadow-card ring-1 ring-line transition duration-300 hover:-translate-y-1 hover:shadow-raised">
                    <div class="relative aspect-[16/10] overflow-hidden">
                        @if ($group['photo'])
                            <img
                                src="{{ $group['photo']['src'] }}"
                                srcset="{{ $group['photo']['srcset'] }}"
                                sizes="(min-width: 1280px) 20vw, (min-width: 640px) 50vw, 100vw"
                                alt="{{ $group['photo']['alt'] }}"
                                loading="lazy"
                                decoding="async"
                                class="absolute inset-0 size-full object-cover transition-transform duration-700 ease-out group-hover/card:scale-105"
                            >
                            <span class="absolute right-3 bottom-3 max-w-[calc(100%-1.5rem)] rounded-full bg-forest/85 px-2.5 py-1 text-xs font-medium text-paper backdrop-blur">{{ $group['photo']['caption'] }}</span>
                        @else
                            <x-home.workspace-art :kind="$group['anchor']" class="absolute inset-0 size-full transition-transform duration-700 ease-out group-hover/card:scale-105" />
                        @endif
                    </div>

                    <div class="relative flex flex-1 flex-col gap-2.5 p-5 pt-9">
                        <span class="absolute -top-6 left-5 flex size-12 items-center justify-center rounded-2xl bg-surface text-leaf-700 shadow-card ring-1 ring-line" aria-hidden="true">
                            <x-dynamic-component :component="'heroicon-o-'.$icon" class="size-6" />
                        </span>
                        <h3 class="font-editorial text-xl font-semibold text-forest">
                            @if ($single && $single['path'])
                                <a href="{{ $single['path'] }}" class="rounded-sm hover:text-leaf-700">{{ $group['title'] }}</a>
                            @else
                                {{ $group['title'] }}
                            @endif
                        </h3>
                        <p class="text-sm text-muted">{{ $group['description'] }}</p>

                        <ul class="flex flex-col gap-2 text-sm">
                            @foreach ($group['categories'] as $category)
                                <li class="flex flex-col gap-0.5">
                                    @if (! $single)
                                        <p class="font-semibold text-forest">
                                            @if ($category['path'])
                                                <a href="{{ $category['path'] }}" class="inline-flex items-center gap-1 hover:text-leaf-700">{{ $category['name'] }} <x-heroicon-m-arrow-right class="size-3.5 text-leaf-700" aria-hidden="true" /></a>
                                            @else
                                                {{ $category['name'] }}
                                            @endif
                                        </p>
                                    @endif
                                    @if ($category['offeredAt'] !== [])
                                        <p class="text-muted">
                                            Available at
                                            @foreach ($category['offeredAt'] as $location)
                                                <a href="{{ $location['path'] }}" class="font-medium text-forest underline decoration-line underline-offset-2 hover:text-leaf-700">SmartGro {{ $location['name'] }}</a>@if (! $loop->last), @endif
                                            @endforeach
                                        </p>
                                    @else
                                        <p class="text-muted">Ask us which locations offer this.</p>
                                    @endif
                                </li>
                            @endforeach
                        </ul>

                        @if ($enquiryOpen)
                            <div class="mt-auto pt-2">
                                <a
                                    href="{{ route('enquiries.create', $single ? ['workspace' => $single['slug']] : [], false) }}"
                                    class="inline-flex items-center gap-1 text-sm font-semibold text-leaf-700 hover:text-leaf-800"
                                    aria-label="Ask SmartGro about {{ \Illuminate\Support\Str::lower($group['title']) }}"
                                >
                                    Ask SmartGro <x-heroicon-m-arrow-right class="size-4" aria-hidden="true" />
                                </a>
                            </div>
                        @endif
                    </div>
                </article>
            @endforeach
        </div>
    </div>
</section>
