@props([
    'hasLocations' => false,
    'tourOffered' => false,
    'explore' => null,
])

{{--
    How to see a space before deciding. Visits and location pages are offered as they work today. 3D walkthroughs
    and floor plans link to the first location page that shows them, once one is published (OD-181,
    LocationExplore::showcase()); until then they are described as not available yet. The illustration beside them is
    labelled as one, never a working preview.
--}}
<section class="py-4 sm:py-6" aria-labelledby="experience-heading">
    <div class="container-page">
        <div class="grid overflow-hidden rounded-4xl bg-forest text-paper lg:grid-cols-12" data-surface="dark" data-reveal>
            <div class="flex flex-col gap-6 px-5 py-8 sm:p-10 lg:col-span-6 lg:p-12">
                <h2 id="experience-heading" class="font-editorial text-display-lg font-semibold text-balance">Experience our spaces before you decide</h2>
                <p class="text-lead text-pretty text-paper/85">See a SmartGro location in person, and start with its page: the workspaces it offers, where it is and how to get there.</p>

                <ul class="flex flex-col gap-5">
                    <li class="flex gap-4">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-white/10 text-leaf-300 ring-1 ring-white/15" aria-hidden="true">
                            <x-heroicon-o-calendar-days class="size-5" />
                        </span>
                        <div class="flex flex-col gap-0.5">
                            <p class="font-semibold">Visit in person</p>
                            <p class="text-sm text-paper/80">{{ $tourOffered ? 'Request a time online. SmartGro confirms it with you.' : 'Ask SmartGro to arrange a visit.' }}</p>
                        </div>
                    </li>
                    <li class="flex gap-4">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-white/10 text-leaf-300 ring-1 ring-white/15" aria-hidden="true">
                            <x-heroicon-o-map class="size-5" />
                        </span>
                        <div class="flex flex-col gap-0.5">
                            <p class="font-semibold">Location pages</p>
                            <p class="text-sm text-paper/80">The workspaces each location offers, its address and, once published, photos and directions.</p>
                        </div>
                    </li>
                    <li class="flex gap-4">
                        <span class="flex size-11 shrink-0 items-center justify-center rounded-full bg-white/10 text-leaf-300 ring-1 ring-white/15" aria-hidden="true">
                            <x-heroicon-o-cube-transparent class="size-5" />
                        </span>
                        <div class="flex flex-col gap-1">
                            @if ($explore)
                                <p class="font-semibold">{{ $explore['walkthrough'] ? '3D walkthroughs and floor plans' : 'Floor plans' }}</p>
                                <p class="text-sm text-paper/80">
                                    {{ match (true) {
                                        $explore['walkthrough'] && $explore['floorPlans'] => 'Turn a floor in 3D, jump to each area and read its floor plans on the location page.',
                                        $explore['walkthrough'] => 'Turn a floor in 3D and jump to each area on the location page.',
                                        default => 'See how a location\'s floors are laid out on its page.',
                                    } }}
                                </p>
                                <a href="{{ $explore['href'] }}" class="inline-flex items-center gap-1 self-start text-sm font-semibold text-leaf-300 underline-offset-4 hover:underline">
                                    {{ $explore['walkthrough'] ? 'Explore '.$explore['name'].' in 3D' : 'See the floor plans of '.$explore['name'] }}
                                    <x-heroicon-m-arrow-right class="size-4" aria-hidden="true" />
                                </a>
                            @else
                                <p class="flex flex-wrap items-center gap-2 font-semibold">
                                    3D walkthroughs and floor plans
                                    <span class="rounded-full bg-white/10 px-2.5 py-0.5 text-xs font-semibold text-leaf-300 ring-1 ring-white/15">Not available yet</span>
                                </p>
                                <p class="text-sm text-paper/80">Interactive 3D walkthroughs and floor plans will be added to location pages when they are ready.</p>
                            @endif
                        </div>
                    </li>
                </ul>

                <div class="flex flex-col gap-3 pt-2 sm:flex-row">
                    @if ($tourOffered)
                        <x-ui.button size="lg" pill variant="inverse" href="/book-a-tour" icon="calendar-days">Request a tour</x-ui.button>
                    @endif
                    @if ($hasLocations)
                        <x-ui.button size="lg" pill variant="inverse-outline" href="/locations">Explore locations</x-ui.button>
                    @elseif (! $tourOffered)
                        <x-ui.button size="lg" pill variant="inverse" href="#contact">Talk to SmartGro</x-ui.button>
                    @endif
                </div>
            </div>

            <figure class="relative min-h-72 overflow-hidden bg-forest-soft sm:min-h-[26rem] lg:col-span-6 lg:min-h-full">
                <x-home.floor-plan-art class="absolute inset-0 size-full" />
                <figcaption class="absolute bottom-4 left-4 inline-flex max-w-[calc(100%-2rem)] items-center gap-1.5 rounded-full bg-forest/90 px-3 py-1.5 text-xs font-medium text-paper ring-1 ring-white/15">
                    <x-heroicon-m-pencil class="size-3.5 shrink-0 text-leaf-300" aria-hidden="true" />
                    Illustration only, not a SmartGro floor plan
                </figcaption>
            </figure>
        </div>
    </div>
</section>
