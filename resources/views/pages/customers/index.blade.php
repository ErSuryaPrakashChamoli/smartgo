{{-- Customer case studies published with consent on file (FR-CMS-7, BR-CMS-7). --}}
<x-layouts::site title="Customer stories" description="How companies work at SmartGro." :canonical="$canonical" :navigation="$navigation">
    <div class="container-page flex flex-col gap-10 py-12 sm:py-16">
        <h1 class="font-display text-display-xl font-semibold text-balance text-forest">Customer stories</h1>
        @if ($caseStudies->isEmpty())
            <p class="text-muted">No customer stories have been published yet.</p>
        @else
            <ul class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($caseStudies as $caseStudy)
                    <li class="flex flex-col gap-3 rounded-2xl bg-surface p-6 shadow-card ring-1 ring-line">
                        <p class="text-eyebrow font-semibold text-leaf-700 uppercase">{{ $caseStudy->company_name }}</p>
                        <h2 class="font-display text-subheading font-semibold text-forest"><a href="{{ $caseStudy->path() }}" class="hover:text-leaf-700">{{ $caseStudy->title }}</a></h2>
                        <p class="text-muted">{{ $caseStudy->summary }}</p>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</x-layouts::site>
