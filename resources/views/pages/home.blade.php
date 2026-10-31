{{--
    The public home page, redesigned after the owner's design reference (OD-139). Every fact comes from
    records; nothing about prices or availability is shown. Images are published photographs of SmartGro
    locations, or illustrations labelled as such (BR-MED, D-12). Links to the enquiry and tour forms
    appear only while visitors can use them. Order (OD-144, OD-146): what SmartGro is, the search, what, where, how,
    seeing a space, who for, reading, questions, contact.
--}}
<x-layouts::site
    :title="null"
    description="SmartGro flexible workspaces: private offices, desks, meeting rooms, virtual offices and managed offices{{ $cityNames === [] ? '' : ' in '.implode(', ', $cityNames) }}."
    :canonical="$canonical"
    :navigation="$navigation"
>
    <x-home.hero
        :properties="$properties"
        :categories="$categories"
        :city-names="$cityNames"
        :photo="$heroPhoto"
        :tour-offered="$tourOffered"
    />

    @if ($categories !== [])
        <section class="py-10 sm:py-14" aria-labelledby="find-heading">
            <div class="container-page flex flex-col gap-5">
                <div class="flex flex-col gap-2">
                    <h2 id="find-heading" class="font-editorial text-display-lg font-semibold text-balance text-forest">Find your workspace</h2>
                    <p class="text-lg text-pretty text-muted">Choose a type of workspace and a location, and see what SmartGro offers there.</p>
                </div>
                <x-home.finder :categories="$categories" :properties="$properties" />
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
