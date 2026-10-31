{{-- The tour request page (IA /book-a-tour, FR-BK-2, the approved tour journey): choose a location, a day and a free time from its tour hours, then contact details. A request holds its place until SmartGro confirms it. Open only once the privacy notice is published and for locations with tour hours (TourForm). Works without JavaScript. --}}
@php
    $error = fn (string $field): ?string => $errors->first($field) ?: null;
    $chosenTime = old('starts_at');
@endphp
<x-layouts::site title="Book a tour" description="Visit a SmartGro location. A tour request is confirmed by SmartGro; nothing is reserved or charged." :noindex="! $isOpen || $properties->isEmpty()" :canonical="$canonical" :navigation="$navigation">
    <div class="container-page">
        <div class="mx-auto flex max-w-4xl flex-col gap-10 py-10 lg:py-16">
            <div class="flex flex-col gap-4">
                <x-site.breadcrumb :items="[['label' => 'Home', 'href' => '/'], ['label' => 'Book a tour']]" />
                <x-ui.section-heading level="h1" eyebrow="Tour" title="Visit a SmartGro location">
                    Choose a time to see the workspace. SmartGro confirms your visit with you. A tour does not reserve a workspace and costs nothing.
                </x-ui.section-heading>
            </div>

            @if (! $isOpen || $properties->isEmpty())
                <x-ui.alert type="info" title="Tour times are not offered online yet">
                    Online tour requests open once SmartGro publishes its privacy notice and its tour hours.
                    @if ($contact !== [])
                        In the meantime, contact SmartGro using the details below.
                    @endif
                </x-ui.alert>
            @elseif ($property === null)
                <nav aria-labelledby="tour-locations-heading" class="flex flex-col gap-4">
                    <h2 id="tour-locations-heading" class="font-display text-heading font-semibold text-forest">Which location would you like to visit?</h2>
                    <ul class="grid gap-4 sm:grid-cols-2">
                        @foreach ($properties as $listed)
                            <li>
                                <a href="{{ route('tours.create', ['property' => $listed->getKey()]) }}" class="flex h-full flex-col gap-1 rounded-2xl bg-surface p-5 shadow-card ring-1 ring-line hover:ring-leaf-700 focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-leaf-700">
                                    <span class="font-semibold text-forest">SmartGro {{ $listed->name }}</span>
                                    <span class="text-sm text-muted">{{ collect([$listed->microMarket?->name, $listed->city->name])->filter()->join(', ') }}</span>
                                </a>
                            </li>
                        @endforeach
                    </ul>
                </nav>
            @else
                <div class="rounded-3xl bg-surface p-6 shadow-card ring-1 ring-line sm:p-10">
                    <div class="flex flex-col gap-6">
                        <div class="flex flex-col gap-1">
                            <h2 class="font-display text-heading font-semibold text-forest">SmartGro {{ $property->name }}</h2>
                            <p class="text-sm text-muted">{{ $property->addressLine() }}</p>
                            @if ($properties->count() > 1)
                                <a href="{{ route('tours.create') }}" class="self-start text-sm font-semibold text-leaf-700 hover:underline">Choose another location</a>
                            @endif
                        </div>

                        @if (session('tour_slot_taken'))
                            <x-ui.alert type="error" title="That time is no longer available">Someone took the last place on that tour a moment ago. Your request was not sent. Please choose another time: your details are still filled in.</x-ui.alert>
                        @endif

                        <nav aria-label="Days with tour times" class="flex flex-col gap-3">
                            <p class="text-sm font-semibold text-forest">Choose a day</p>
                            @if ($days === [])
                                <x-ui.alert type="info" title="No tour times in the next {{ config('tours.window_days') }} days">Send an enquiry and SmartGro will suggest a time.</x-ui.alert>
                            @else
                                <ul class="flex flex-wrap gap-2">
                                    @foreach ($days as $option)
                                        <li>
                                            <a href="{{ route('tours.create', ['property' => $property->getKey(), 'date' => $option['date']]) }}"
                                                @if ($option['date'] === $day) aria-current="date" @endif
                                                @class(['flex h-11 items-center rounded-lg px-3 text-sm font-semibold tabular-nums ring-1 ring-inset focus-visible:outline-3 focus-visible:outline-offset-2 focus-visible:outline-leaf-700',
                                                    'bg-leaf-700 text-white ring-leaf-700' => $option['date'] === $day,
                                                    'bg-surface text-forest ring-control hover:ring-leaf-700' => $option['date'] !== $day && $option['free'] > 0,
                                                    'bg-paper text-muted ring-line' => $option['date'] !== $day && $option['free'] === 0])>
                                                {{ $option['label'] }}<span class="sr-only">{{ $option['free'] === 0 ? ', fully booked' : '' }}</span>
                                            </a>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                        </nav>

                        @if ($day !== null)
                            <form method="POST" action="{{ route('tours.store') }}" novalidate class="flex flex-col gap-6">
                                @csrf
                                <input type="hidden" name="property" value="{{ $property->getKey() }}">
                                <input type="hidden" name="date" value="{{ $day }}">
                                <input type="hidden" name="started" value="{{ $started }}">
                                <input type="hidden" name="from" value="{{ old('from', $from) }}">
                                @foreach ($utm as $key => $utmValue)
                                    <input type="hidden" name="{{ $key }}" value="{{ old($key, $utmValue) }}">
                                @endforeach
                                <div class="hidden" aria-hidden="true">
                                    <label for="tour-website">Leave this empty</label>
                                    <input type="text" id="tour-website" name="website" tabindex="-1" autocomplete="off">
                                </div>

                                @if ($errors->any() && ! session('tour_slot_taken'))
                                    <x-ui.alert type="error" :title="'Please check '.$errors->count().' '.\Illuminate\Support\Str::plural('field', $errors->count())">
                                        <ul class="list-disc pl-4">
                                            @foreach ($errors->all() as $message)
                                                <li>{{ $message }}</li>
                                            @endforeach
                                        </ul>
                                    </x-ui.alert>
                                @endif

                                <x-ui.slot-picker name="starts_at" :legend="'Choose a time on '.\Carbon\CarbonImmutable::parse($day)->format('l j F').' (India time)'"
                                    :state="collect($times)->where('disabled', false)->isEmpty() ? 'empty' : 'ready'"
                                    :slots="collect($times)->map(fn ($time) => [...$time, 'checked' => $time['value'] === $chosenTime])->all()" />
                                @if ($error('starts_at') && ! session('tour_slot_taken'))
                                    <p class="-mt-4 text-sm font-medium text-danger">{{ $error('starts_at') }}</p>
                                @endif

                                <div class="grid gap-4 sm:grid-cols-2">
                                    <x-form.field label="Full name" for="tour-name" required :error="$error('name')">
                                        <x-form.input id="tour-name" name="name" autocomplete="name" maxlength="120" :value="old('name')" :invalid="$error('name') !== null" />
                                    </x-form.field>
                                    <x-form.field label="Email" for="tour-email" hint="Email or phone: one is enough." :error="$error('email')">
                                        <x-form.input id="tour-email" name="email" type="email" autocomplete="email" maxlength="191" :value="old('email')" :invalid="$error('email') !== null" described-by="tour-email-hint" />
                                    </x-form.field>
                                    <x-form.field label="Phone" for="tour-phone" :error="$error('phone')">
                                        <x-form.input id="tour-phone" name="phone" type="tel" autocomplete="tel" maxlength="20" :value="old('phone')" :invalid="$error('phone') !== null" />
                                    </x-form.field>
                                    <x-form.field label="Company" for="tour-company" :error="$error('company')">
                                        <x-form.input id="tour-company" name="company" autocomplete="organization" maxlength="120" :value="old('company')" :invalid="$error('company') !== null" />
                                    </x-form.field>
                                    <x-form.field label="Number of people" for="tour-visitors" required :error="$error('visitors')">
                                        <x-form.select id="tour-visitors" name="visitors" :selected="old('visitors', '1')" :invalid="$error('visitors') !== null"
                                            :options="collect(range(1, $maxVisitors))->mapWithKeys(fn ($count) => [$count => (string) $count])->all()" />
                                    </x-form.field>
                                </div>

                                <x-form.field label="Anything we should know?" for="tour-notes" :error="$error('notes')">
                                    <x-form.textarea id="tour-notes" name="notes" maxlength="1000" placeholder="Team size, the workspace you are interested in, access needs" :invalid="$error('notes') !== null">{{ old('notes') }}</x-form.textarea>
                                </x-form.field>

                                <div class="flex flex-col gap-1.5">
                                    <x-form.checkbox id="tour-consent" name="consent" value="1" :checked="(bool) old('consent')" :label="\App\Services\Website\EnquiryForm::CONSENT_TEXT" />
                                    <p class="pl-8 text-sm text-muted">
                                        How SmartGro uses your details: <a href="/{{ $notice->slug }}" class="font-semibold text-leaf-700 hover:underline">{{ $notice->title }}</a>.
                                    </p>
                                    @if ($error('consent'))
                                        <p class="flex items-center gap-1.5 pl-8 text-sm font-medium text-danger">
                                            <x-heroicon-m-exclamation-circle class="size-4 shrink-0" aria-hidden="true" />{{ $error('consent') }}
                                        </p>
                                    @endif
                                </div>

                                <div class="flex flex-col gap-3 border-t border-line pt-5 sm:flex-row sm:items-center sm:justify-between">
                                    <p class="text-sm text-muted">This sends a request. SmartGro confirms the time with you. Nothing is reserved or charged.</p>
                                    <x-ui.button type="submit" size="lg" icon="arrow-right">Request a tour</x-ui.button>
                                </div>
                            </form>
                        @endif
                    </div>
                </div>
            @endif
        </div>
    </div>

    @if (! $isOpen || $properties->isEmpty())
        <x-site.contact-section :contact="$contact" />
    @endif
</x-layouts::site>
