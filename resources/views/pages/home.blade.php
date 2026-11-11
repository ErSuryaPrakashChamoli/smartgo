{{--
    The public home page, redesigned after the owner's design reference (OD-139). Every fact comes from
    records; nothing about prices or availability is shown. Images are published photographs of SmartGro
    locations, or illustrations labelled as such (BR-MED, D-12). Links to the enquiry and tour forms
    appear only while visitors can use them. Order (OD-144, OD-146, OD-169): what SmartGro is (the banner carousel once a
    banner is published, else the single hero), the highlights strip, the search, what, where, how, seeing a space, who
    for, reading, questions, contact.
--}}
<x-layouts::site
    :title="null"
    description="SmartGro flexible workspaces: private offices, desks, meeting rooms, virtual offices and managed offices{{ $cityNames === [] ? '' : ' in '.implode(', ', $cityNames) }}."
    :canonical="$canonical"
    :navigation="$navigation"
>
    @if ($slides !== [])
        <x-home.carousel :slides="$slides" />
    @else
        <x-home.hero
            :properties="$properties"
            :categories="$categories"
            :city-names="$cityNames"
            :photo="$heroPhoto"
            :tour-offered="$tourOffered"
        />
    @endif

    @if ($highlights['items'] !== [])
        <x-home.highlights :items="$highlights['items']" :sample="$highlights['sample']" />
    @endif

    {{-- A compact band (OD-175): from 1024 px the heading sits beside the search, so the search shows on the first screen under the banners and the strip. --}}
    @if ($categories !== [])
        <section class="py-8 sm:py-10 lg:py-7" aria-labelledby="find-heading">
            <div class="container-page grid gap-5 lg:grid-cols-12 lg:items-center lg:gap-8">
                <div class="flex flex-col gap-1.5 lg:col-span-4">
                    <h2 id="find-heading" class="font-editorial text-display-lg font-semibold text-balance text-forest">Find your workspace</h2>
                    <p class="text-pretty text-muted sm:text-lg lg:text-base">Choose a type of workspace and a location, and see what SmartGro offers there.</p>
                </div>
                <x-home.finder :categories="$categories" :properties="$properties" class="lg:col-span-8" />
            </div>
        </section>
    @endif

    @if ($groups !== [])
        <x-home.workspaces :groups="$groups" :enquiry-open="$enquiryOpen" />
    @endif

    @if ($properties->isNotEmpty())
        <x-home.locations :properties="$properties" :photos="$propertyPhotos" :city-names="$cityNames" />
    @endif

    <x-home.steps :tour-offered="$tourOffered" :enquiry-open="$enquiryOpen" />

    <x-home.experience :has-locations="$properties->isNotEmpty()" :tour-offered="$tourOffered" />

    @if ($segments !== [])
        <x-home.segments :segments="$segments" />
    @endif

    @if ($posts->isNotEmpty())
        <x-home.insights :posts="$posts" />
    @endif

    @if ($faqs->isNotEmpty())
        <x-home.questions :faqs="$faqs" />
    @endif

    <x-home.conversion
        :contact="$contact"
        :enquiry-open="$enquiryOpen"
        :tour-offered="$tourOffered"
        :has-locations="$properties->isNotEmpty()"
        :has-finder="$categories !== []"
    />
</x-layouts::site>
