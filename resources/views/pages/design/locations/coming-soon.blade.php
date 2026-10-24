<x-layouts::site
    :title="'SmartGro in '.$city['name'].' (design preview)'"
    :noindex="true"
    :preview="true"
    :home-href="route('design.home')"
    :categories="$categories"
    :properties="$properties"
>
    <div class="container-page flex flex-col gap-10 py-10 lg:py-16">
        <x-site.design-note title="Example state">
            This is how {{ $city['name'] }} would appear if an administrator made it visible before a property opens there (OD-18, OD-22). It is hidden by default. The page never lists properties, prices or inventory, is excluded from search engines, and every submission creates a "city waitlist" lead (BR-PROP-4, BR-CRM-1).
        </x-site.design-note>

        <div class="grid gap-10 lg:grid-cols-12 lg:gap-14">
            <div class="flex flex-col gap-6 lg:col-span-5">
                <x-site.breadcrumb :items="[['label' => 'Locations', 'href' => route('design.locations')], ['label' => $city['name']]]" />
                <x-ui.badge tone="info" icon="clock" class="self-start">Coming soon</x-ui.badge>
                <h1 class="font-display text-display-xl font-semibold text-balance text-forest">SmartGro in {{ $city['name'] }}</h1>
                <p class="text-lead text-muted">
                    There is no SmartGro workspace in {{ $city['name'] }} yet. Register your interest and we will let you know if one opens here.
                </p>
                <div class="rounded-2xl bg-surface p-5 ring-1 ring-line">
                    <p class="font-semibold text-forest">Need a workspace now?</p>
                    <p class="mt-1 text-sm text-muted">SmartGro currently operates in Noida.</p>
                    <x-ui.button variant="ghost" :href="route('design.city', 'noida')" icon="arrow-right" class="-ml-5 mt-2">See properties in Noida</x-ui.button>
                </div>
            </div>

            <div class="flex flex-col gap-6 lg:col-span-7">
                <form action="#" class="flex flex-col gap-5 rounded-3xl bg-surface p-6 shadow-card ring-1 ring-line sm:p-8">
                    <h2 class="font-display text-heading font-semibold text-forest">Register your interest</h2>
                    <div class="grid gap-4 sm:grid-cols-2">
                        <x-form.field label="Full name" for="interest-name" required>
                            <x-form.input id="interest-name" autocomplete="name" />
                        </x-form.field>
                        <x-form.field label="Work email" for="interest-email" required>
                            <x-form.input id="interest-email" type="email" autocomplete="email" />
                        </x-form.field>
                        <x-form.field label="Mobile number" for="interest-phone" hint="Optional">
                            <x-form.input id="interest-phone" type="tel" autocomplete="tel" described-by="interest-phone-hint" />
                        </x-form.field>
                        <x-form.field label="Team size" for="interest-size">
                            <x-form.select id="interest-size" placeholder="Choose a range" :options="['1' => 'Just me', '2-5' => '2–5 people', '6-20' => '6–20 people', '21-49' => '21–49 people', '50+' => '50 or more']" />
                        </x-form.field>
                    </div>
                    <x-form.field label="What are you looking for?" for="interest-type">
                        <x-form.select id="interest-type" placeholder="Any workspace type" :options="collect($categories)->pluck('name', 'slug')->all()" />
                    </x-form.field>
                    <x-form.checkbox id="interest-consent" :label="'Contact me if SmartGro opens in '.$city['name']" hint="Privacy notice to be added (D-15)." />
                    <x-ui.button type="submit" size="lg" class="self-start">Register interest</x-ui.button>
                </form>

                <div class="flex flex-col gap-3 rounded-3xl border border-dashed border-control/60 p-6">
                    <p class="text-sm font-semibold text-muted">After submitting</p>
                    <x-ui.alert type="success" title="Thank you: your interest is registered">
                        We have noted your interest in {{ $city['name'] }}. This does not reserve a workspace. We will contact you only about a SmartGro opening there.
                    </x-ui.alert>
                </div>
            </div>
        </div>
    </div>
</x-layouts::site>
