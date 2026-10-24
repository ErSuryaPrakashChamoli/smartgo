<x-layouts::site
    title="Home (design preview)"
    :noindex="true"
    :preview="true"
    :home-href="route('design.home')"
    :categories="$categories"
    :properties="$properties"
>
    {{-- Hero --}}
    <section class="relative isolate overflow-hidden">
        <div class="absolute inset-x-0 top-0 -z-10 h-[32rem] bg-linear-to-b from-mist/70 to-paper" aria-hidden="true"></div>
        <div class="container-page grid items-center gap-10 py-12 sm:py-16 lg:grid-cols-12 lg:gap-12 lg:py-20">
            <div class="flex flex-col gap-6 lg:col-span-6">
                <p class="text-eyebrow font-semibold text-leaf-700 uppercase">Work smart. Grow together.</p>
                <h1 class="font-display text-display-2xl font-semibold text-balance text-forest">
                    Workspaces that grow <span class="text-leaf-600">with your team</span>
                </h1>
                <p class="max-w-xl text-lead text-pretty text-muted">
                    Private cabins, desks and meeting rooms across four SmartGro properties in Noida. Visit one before you decide.
                </p>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <x-ui.button size="lg" href="#" icon="calendar-days">Book a tour</x-ui.button>
                    <x-ui.button size="lg" variant="secondary" href="#workspaces">Explore workspaces</x-ui.button>
                </div>
                <dl class="mt-2 grid grid-cols-3 gap-4 border-t border-line pt-6 sm:max-w-md">
                    <div>
                        <dt class="text-sm text-muted">Properties</dt>
                        <dd class="font-display text-heading font-semibold text-forest">{{ count($properties) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted">Workspace types</dt>
                        <dd class="font-display text-heading font-semibold text-forest">{{ count($categories) }}</dd>
                    </div>
                    <div>
                        <dt class="text-sm text-muted">City</dt>
                        <dd class="font-display text-heading font-semibold text-forest">Noida</dd>
                    </div>
                </dl>
            </div>

            <div class="relative lg:col-span-6">
                <x-ui.photo class="aspect-[4/3] w-full rounded-3xl shadow-raised lg:aspect-[5/4]" label="Hero photography to be supplied by SmartGro" />
                <form class="relative -mt-16 mx-4 flex flex-col gap-4 rounded-2xl bg-surface p-5 shadow-raised ring-1 ring-line sm:mx-8 lg:absolute lg:-bottom-8 lg:-left-10 lg:mx-0 lg:mt-0 lg:w-[22rem]" action="#">
                    <p class="font-display text-subheading font-semibold text-forest">Find a workspace</p>
                    <x-form.field label="Workspace type" for="hero-type">
                        <x-form.select id="hero-type" placeholder="Any workspace type" :options="collect($categories)->pluck('name', 'name')->all()" />
                    </x-form.field>
                    <x-form.field label="Location" for="hero-location">
                        <x-form.select id="hero-location" placeholder="Any Noida property" :options="collect($properties)->mapWithKeys(fn ($property) => [$property['name'] => $property['name'].', '.$property['area']])->all()" />
                    </x-form.field>
                    <x-ui.button type="submit" icon="arrow-right">Show options</x-ui.button>
                </form>
            </div>
        </div>
    </section>

    {{-- Workspace types --}}
    <section id="workspaces" class="scroll-mt-24 pt-12 pb-16 sm:pt-16 sm:pb-20 lg:pt-20 lg:pb-28">
        <div class="container-page flex flex-col gap-10">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <x-ui.section-heading eyebrow="Workspaces" title="Choose how your team works">
                    From a single desk for the day to an office suite for a growing company.
                </x-ui.section-heading>
                <x-ui.button variant="ghost" href="#" icon="arrow-right" class="-ml-5 self-start lg:ml-0 lg:self-auto">All workspace types</x-ui.button>
            </div>
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($categories as $category)
                    <x-card.workspace :name="$category['name']" :icon="$category['icon']" :class="$loop->last ? 'lg:col-span-2' : ''">
                        {{ $category['summary'] }}
                    </x-card.workspace>
                @endforeach
            </div>
        </div>
    </section>

    {{-- Locations --}}
    <section class="bg-surface py-16 sm:py-20 lg:py-28">
        <div class="container-page flex flex-col gap-10">
            <div class="flex flex-col gap-6 lg:flex-row lg:items-end lg:justify-between">
                <x-ui.section-heading eyebrow="Locations" title="Four properties in Noida">
                    Each property is its own SmartGro centre. Compare them side by side, then visit the one that suits you.
                </x-ui.section-heading>
                <x-ui.button variant="ghost" href="#" icon="map-pin" class="-ml-5 self-start lg:ml-0 lg:self-auto">View on a map</x-ui.button>
            </div>
            <ul class="-mx-5 flex snap-x snap-mandatory gap-4 overflow-x-auto px-5 pb-2 sm:mx-0 sm:grid sm:grid-cols-2 sm:gap-5 sm:overflow-visible sm:px-0 sm:pb-0 lg:grid-cols-4" aria-label="SmartGro properties in Noida">
                @foreach ($properties as $property)
                    <li class="w-[82%] shrink-0 snap-start sm:w-auto">
                        <x-card.property :name="$property['name']" :area="$property['area']" :city="$property['city']" class="h-full" />
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- 3D teaser --}}
    <section class="py-16 sm:py-20 lg:py-28">
        <div class="container-page">
            <div data-surface="dark" class="grid items-center gap-10 overflow-hidden rounded-3xl bg-forest-soft p-6 sm:p-10 lg:grid-cols-2 lg:p-14">
                <div class="flex flex-col gap-6">
                    <span class="inline-flex items-center gap-1.5 self-start rounded-full bg-white/10 px-3 py-1 text-xs font-semibold text-leaf-300">
                        <x-heroicon-m-cube-transparent class="size-4" aria-hidden="true" />
                        Coming with the 3D digital twin
                    </span>
                    <x-ui.section-heading eyebrow="Explore in 3D" title="Walk the floor before you visit" tone="dark">
                        Move through each floor, see which spaces are free, and choose the one that fits your team.
                    </x-ui.section-heading>
                    <div><x-ui.button variant="inverse" href="#" icon="arrow-right">Explore a property in 3D</x-ui.button></div>
                </div>
                <svg viewBox="0 0 480 320" class="w-full text-leaf-300" role="img" aria-label="Illustration of an isometric floor plan">
                    <g fill="none" stroke="currentColor" stroke-width="1.5" stroke-linejoin="round">
                        <path d="M240 40 440 150 240 260 40 150Z" fill="rgb(132 189 83 / 0.08)" />
                        <path d="M240 40v40M440 150v40M240 260v40M40 150v40M40 190l200 110 200-110" stroke-opacity="0.6" />
                        <path d="M140 95 340 205M190 68l200 110M90 123l200 110M290 68 90 178M340 95 140 205" stroke-opacity="0.35" />
                        <path d="M240 95 300 128 240 161 180 128Z" fill="rgb(132 189 83 / 0.35)" />
                    </g>
                    <g fill="currentColor">
                        <circle cx="240" cy="128" r="5" />
                        <circle cx="330" cy="160" r="4" fill-opacity="0.6" />
                        <circle cx="150" cy="150" r="4" fill-opacity="0.6" />
                    </g>
                </svg>
            </div>
        </div>
    </section>

    {{-- Customer segments --}}
    <section class="pb-16 sm:pb-20 lg:pb-28">
        <div class="container-page flex flex-col gap-10">
            <x-ui.section-heading eyebrow="Who it's for" title="Built for the way you work" />
            <ul class="grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                @foreach ($segments as $segment)
                    <li>
                        <a href="#" class="group flex h-full items-center justify-between gap-3 rounded-xl bg-surface px-4 py-4 text-sm font-semibold text-forest ring-1 ring-line hover:ring-leaf-300">
                            {{ $segment }}
                            <x-heroicon-m-arrow-right class="size-4 shrink-0 text-leaf-700 transition-transform group-hover:translate-x-0.5" aria-hidden="true" />
                        </a>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    {{-- How a tour works --}}
    <section class="bg-surface py-16 sm:py-20 lg:py-28">
        <div class="container-page flex flex-col gap-12">
            <x-ui.section-heading eyebrow="Visit us" title="How a tour works" align="center" />
            <ol class="grid gap-6 md:grid-cols-3">
                @foreach ([
                    ['icon' => 'calendar-days', 'title' => 'Pick a property and a time', 'text' => 'Choose a slot that suits you, online or by phone.'],
                    ['icon' => 'key', 'title' => 'Walk through the spaces', 'text' => 'See the workspaces, meeting rooms and shared areas in person.'],
                    ['icon' => 'document-text', 'title' => 'Get a proposal', 'text' => 'Receive options for your team size and move-in date.'],
                ] as $step)
                    <li class="relative flex flex-col gap-3 rounded-2xl bg-paper p-6 ring-1 ring-line">
                        <span class="font-display text-display-lg font-semibold text-leaf-600">0{{ $loop->iteration }}</span>
                        <x-dynamic-component :component="'heroicon-o-'.$step['icon']" class="size-6 text-leaf-700" aria-hidden="true" />
                        <h3 class="font-display text-subheading font-semibold text-forest">{{ $step['title'] }}</h3>
                        <p class="text-muted">{{ $step['text'] }}</p>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    {{-- Managed offices --}}
    <section class="py-16 sm:py-20 lg:py-28">
        <div class="container-page">
            <x-site.cta-band eyebrow="Managed offices" title="Planning space for a larger team?">
                Tell us your seat count, timeline and preferred location, and we will prepare options across our Noida properties.
                <x-slot:actions>
                    <x-ui.button variant="inverse" size="lg" href="#">Talk to us</x-ui.button>
                    <x-ui.button variant="inverse-outline" size="lg" href="#">About managed offices</x-ui.button>
                </x-slot:actions>
            </x-site.cta-band>
        </div>
    </section>

    {{-- FAQs --}}
    <section class="pb-16 sm:pb-20 lg:pb-28">
        <div class="container-page grid gap-10 lg:grid-cols-12">
            <div class="lg:col-span-4">
                <x-ui.section-heading eyebrow="FAQs" title="Questions, answered" />
            </div>
            <div class="lg:col-span-8">
                @foreach ([
                    'Can I visit a property before booking?',
                    'Which workspace types are available at each property?',
                    'How is pricing worked out?',
                    'Do you offer a virtual office address?',
                ] as $question)
                    <x-ui.faq-item :question="$question" :class="$loop->first ? 'border-t' : ''">
                        Answer to be supplied by SmartGro.
                    </x-ui.faq-item>
                @endforeach
            </div>
        </div>
    </section>
</x-layouts::site>
