<x-layouts::site
    title="Foundations (design preview)"
    :noindex="true"
    :preview="true"
    :home-href="route('design.home')"
    :categories="$categories"
    :properties="$properties"
>
    <div class="container-page flex flex-col gap-16 py-12 lg:py-16">
        <div class="flex flex-col gap-4">
            <x-site.breadcrumb :items="[['label' => 'Design', 'href' => route('design.index')], ['label' => 'Foundations']]" />
            <x-ui.section-heading level="h1" eyebrow="Phase 2 · Steps 2.1 and 2.2" title="Foundations">
                Colours sampled from the SmartGro logo, the type scale and logo usage. Values on this page are read from the stylesheet, so they always match what ships.
            </x-ui.section-heading>
        </div>

        {{-- Logo --}}
        <section class="flex flex-col gap-6" aria-labelledby="logo">
            <h2 id="logo" class="font-display text-heading font-semibold text-forest">Logo</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                @foreach (['bg-surface' => 'On white', 'bg-paper' => 'On paper', 'bg-mist' => 'On mist'] as $background => $label)
                    <figure class="flex flex-col gap-2">
                        <div class="{{ $background }} flex aspect-[16/10] items-center justify-center rounded-2xl ring-1 ring-line">
                            <x-site.brand-logo size="lg" />
                        </div>
                        <figcaption class="text-sm text-muted">{{ $label }}</figcaption>
                    </figure>
                @endforeach
            </div>
            <div class="grid gap-4 sm:grid-cols-2">
                @foreach (['bg-forest' => 'Reversed, on forest', 'bg-forest-soft' => 'Reversed, on forest soft'] as $background => $label)
                    <figure class="flex flex-col gap-2">
                        <div class="{{ $background }} flex aspect-[16/10] items-center justify-center rounded-2xl">
                            <x-site.brand-logo size="lg" reversed />
                        </div>
                        <figcaption class="text-sm text-muted">{{ $label }}</figcaption>
                    </figure>
                @endforeach
            </div>
            <x-ui.alert type="warning" title="Tagline on dark backgrounds">
                The reversed logo changes only the dark lettering and buildings to Warm White (OD-45). The tagline keeps its original dark grey, which reaches only 1.55:1 on forest, so it is not readable there. Use the reversed logo on dark backgrounds only once SmartGro decides how the tagline should appear.
            </x-ui.alert>
            <div class="flex flex-wrap items-end gap-8 rounded-2xl bg-surface p-6 ring-1 ring-line">
                @foreach ([16, 32, 48] as $size)
                    <figure class="flex flex-col items-center gap-2">
                        <img src="{{ asset('brand/smartgro-mark.png') }}" alt="" width="{{ $size }}" height="{{ $size }}">
                        <figcaption class="text-xs text-muted">{{ $size }} px tab icon</figcaption>
                    </figure>
                @endforeach
                <figure class="flex flex-col items-center gap-2">
                    <img src="{{ asset('brand/apple-touch-icon.png') }}" alt="" width="90" height="90" class="rounded-[1.25rem] ring-1 ring-line">
                    <figcaption class="text-xs text-muted">Home-screen icon (180 px)</figcaption>
                </figure>
                <p class="max-w-sm text-sm text-muted">Cropped from the "SG" symbol of the supplied logo, unchanged in shape and colour (OD-44). Regenerate with <code class="font-mono text-xs">php artisan brand:renditions</code> whenever the logo file changes.</p>
            </div>
        </section>

        {{-- Colour --}}
        <section class="flex flex-col gap-6" aria-labelledby="colour">
            <h2 id="colour" class="font-display text-heading font-semibold text-forest">Colour</h2>
            <ul class="grid grid-cols-2 gap-4 sm:grid-cols-3 lg:grid-cols-5">
                @foreach ($colors as $name => $hex)
                    <li class="overflow-hidden rounded-xl bg-surface ring-1 ring-line">
                        <div class="h-20 border-b border-line" style="background-color: {{ $hex }}"></div>
                        <div class="flex flex-col gap-0.5 p-3">
                            <span class="text-sm font-semibold text-forest">{{ $name }}</span>
                            <span class="font-mono text-xs text-muted uppercase">{{ $hex }}</span>
                        </div>
                    </li>
                @endforeach
            </ul>
        </section>

        {{-- Contrast --}}
        <section class="flex flex-col gap-6" aria-labelledby="contrast">
            <div class="flex flex-col gap-2">
                <h2 id="contrast" class="font-display text-heading font-semibold text-forest">Contrast of every pairing in use</h2>
                <p class="max-w-3xl text-muted">WCAG 2.2 AA: 4.5:1 for normal text, 3:1 for large text, icons and control borders. The same list is checked by an automated test.</p>
            </div>
            <div class="overflow-x-auto rounded-2xl bg-surface ring-1 ring-line">
                <table class="w-full min-w-[44rem] text-left text-sm">
                    <thead class="border-b border-line text-muted">
                        <tr>
                            <th scope="col" class="px-5 py-3 font-semibold">Sample</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Text on background</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Used for</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Ratio</th>
                            <th scope="col" class="px-5 py-3 font-semibold">Result</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-line">
                        @foreach ($pairings as $pairing)
                            <tr>
                                <td class="px-5 py-3">
                                    <span class="inline-flex rounded-md px-3 py-1.5 font-semibold" style="color: {{ $colors[$pairing['foreground']] }}; background-color: {{ $colors[$pairing['background']] }}">Aa</span>
                                </td>
                                <td class="px-5 py-3 text-ink">{{ $pairing['foreground'] }} on {{ $pairing['background'] }}</td>
                                <td class="px-5 py-3 text-muted">{{ $pairing['usage'] }}</td>
                                <td class="px-5 py-3 font-semibold text-forest tabular-nums">{{ number_format($pairing['ratio'], 2) }} : 1</td>
                                <td class="px-5 py-3">
                                    <x-ui.availability :status="$pairing['passes'] ? 'available' : 'unavailable'">
                                        {{ $pairing['passes'] ? 'Pass' : 'Fail' }} (≥ {{ $pairing['minimum'] }})
                                    </x-ui.availability>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </section>

        {{-- Type --}}
        <section class="flex flex-col gap-6" aria-labelledby="type">
            <div class="flex flex-col gap-2">
                <h2 id="type" class="font-display text-heading font-semibold text-forest">Typography</h2>
                <p class="max-w-3xl text-muted">Sora for headings, Inter for text, forms and prices (Inter carries the ₹ sign). Both are open-licensed and served from this site, with no third-party font requests. Heading sizes scale smoothly between 360 px and 1440 px screens.</p>
            </div>
            <div class="flex flex-col divide-y divide-line rounded-2xl bg-surface ring-1 ring-line">
                @foreach ([
                    ['token' => 'display-2xl', 'class' => 'font-display text-display-2xl font-semibold', 'use' => 'Home hero heading', 'sample' => 'Workspaces that grow'],
                    ['token' => 'display-xl', 'class' => 'font-display text-display-xl font-semibold', 'use' => 'Page heading', 'sample' => 'Coworking in Noida'],
                    ['token' => 'display-lg', 'class' => 'font-display text-display-lg font-semibold', 'use' => 'Section heading', 'sample' => 'Choose how your team works'],
                    ['token' => 'heading', 'class' => 'font-display text-heading font-semibold', 'use' => 'Sub-section heading', 'sample' => 'Meeting rooms at A-70'],
                    ['token' => 'subheading', 'class' => 'font-display text-subheading font-semibold', 'use' => 'Card title', 'sample' => 'Private cabins'],
                    ['token' => 'lead', 'class' => 'text-lead', 'use' => 'Introductions', 'sample' => 'Private cabins, desks and meeting rooms in Noida.'],
                    ['token' => 'base', 'class' => 'text-base', 'use' => 'Body text', 'sample' => 'Book a tour and see the workspace before you decide. ₹ 1,25,000'],
                    ['token' => 'sm', 'class' => 'text-sm', 'use' => 'Meta and captions', 'sample' => 'Sector 2, Noida'],
                    ['token' => 'eyebrow', 'class' => 'text-eyebrow font-semibold uppercase', 'use' => 'Eyebrow label', 'sample' => 'Work smart. Grow together.'],
                ] as $style)
                    <div class="grid gap-2 p-5 md:grid-cols-[12rem_1fr] md:items-baseline md:gap-6">
                        <div class="flex flex-col">
                            <span class="font-mono text-xs text-leaf-700">{{ $style['token'] }}</span>
                            <span class="text-sm text-muted">{{ $style['use'] }}</span>
                        </div>
                        <p class="{{ $style['class'] }} text-forest">{{ $style['sample'] }}</p>
                    </div>
                @endforeach
            </div>
        </section>

        {{-- Shape --}}
        <section class="flex flex-col gap-6" aria-labelledby="shape">
            <h2 id="shape" class="font-display text-heading font-semibold text-forest">Shape, depth and grid</h2>
            <div class="grid gap-4 sm:grid-cols-3">
                <div class="flex h-32 items-end rounded-lg bg-surface p-4 text-sm text-muted ring-1 ring-line">Controls: 8 px radius</div>
                <div class="flex h-32 items-end rounded-2xl bg-surface p-4 text-sm text-muted shadow-card ring-1 ring-line">Cards: 16 px radius, card shadow</div>
                <div class="flex h-32 items-end rounded-3xl bg-surface p-4 text-sm text-muted shadow-raised ring-1 ring-line">Feature panels: 24 px radius, raised shadow</div>
            </div>
            <p class="max-w-3xl text-muted">Content width up to 1280 px with 20 px side margins on phones, 32 px on tablets and 40 px on desktops. Spacing follows a 4 px base.</p>
        </section>
    </div>
</x-layouts::site>
