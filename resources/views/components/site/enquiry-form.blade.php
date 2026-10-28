@props([
    'properties' => collect(),
    'categories' => collect(),
    'segments' => collect(),
    'teamSizes' => [],
    'selected' => [],
    'started',
    'notice',
    'from' => null,
    'utm' => [],
    'cityName' => null,
])

{{--
    The enquiry form (FR-BK-1, the approved enquiry journey): at most eight visible fields, the context
    carried in, errors listed at the top and next to each field, and a clear "nothing is reserved". The
    hidden "website" field is a trap for bots; "started" notes when the form was shown. With a city, it
    registers interest in a city where SmartGro has no location yet (OD-22).
--}}
@php
    $value = fn (string $field): mixed => old($field, $selected[$field] ?? null);
    $error = fn (string $field): ?string => $errors->first($field) ?: null;
    $isWaitlist = $cityName !== null;
@endphp
<form method="POST" action="{{ route('enquiries.store') }}" novalidate {{ $attributes->class(['flex flex-col gap-6']) }}>
    @csrf
    <input type="hidden" name="started" value="{{ $started }}">
    <input type="hidden" name="from" value="{{ old('from', $from) }}">
    @foreach ($utm as $key => $utmValue)
        <input type="hidden" name="{{ $key }}" value="{{ old($key, $utmValue) }}">
    @endforeach
    @if ($isWaitlist)
        <input type="hidden" name="city" value="{{ $selected['city'] ?? '' }}">
    @endif
    <div class="hidden" aria-hidden="true">
        <label for="enquiry-website">Leave this empty</label>
        <input type="text" id="enquiry-website" name="website" tabindex="-1" autocomplete="off">
    </div>

    @if ($errors->any())
        <x-ui.alert type="error" :title="'Please check '.$errors->count().' '.\Illuminate\Support\Str::plural('field', $errors->count())">
            <ul class="list-disc pl-4">
                @foreach ($errors->all() as $message)
                    <li>{{ $message }}</li>
                @endforeach
            </ul>
        </x-ui.alert>
    @endif

    @unless ($isWaitlist)
        <div class="grid gap-4 sm:grid-cols-2">
            <x-form.field label="Location" for="enquiry-property" :error="$error('property')">
                <x-form.select id="enquiry-property" name="property" placeholder="Any SmartGro location" :selected="$value('property')" :invalid="$error('property') !== null"
                    :options="$properties->mapWithKeys(fn ($property) => [$property->getKey() => 'SmartGro '.$property->name.', '.collect([$property->microMarket?->name, $property->city->name])->filter()->join(', ')])->all()" />
            </x-form.field>
            <x-form.field label="Workspace type" for="enquiry-workspace" :error="$error('workspace')">
                <x-form.select id="enquiry-workspace" name="workspace" placeholder="Any workspace type" :selected="$value('workspace')" :invalid="$error('workspace') !== null"
                    :options="$categories->pluck('name', 'slug')->all()" />
            </x-form.field>
        </div>
    @endunless

    <div class="grid gap-4 sm:grid-cols-2">
        <x-form.field label="Full name" for="enquiry-name" required :error="$error('name')">
            <x-form.input id="enquiry-name" name="name" autocomplete="name" maxlength="120" :value="old('name')" :invalid="$error('name') !== null" :described-by="$error('name') ? 'enquiry-name-error' : null" />
        </x-form.field>
        <x-form.field label="Email" for="enquiry-email" hint="Email or phone: one is enough." :error="$error('email')">
            <x-form.input id="enquiry-email" name="email" type="email" autocomplete="email" maxlength="191" :value="old('email')" :invalid="$error('email') !== null" :described-by="$error('email') ? 'enquiry-email-error' : 'enquiry-email-hint'" />
        </x-form.field>
        <x-form.field label="Phone" for="enquiry-phone" :error="$error('phone')">
            <x-form.input id="enquiry-phone" name="phone" type="tel" autocomplete="tel" maxlength="20" :value="old('phone')" :invalid="$error('phone') !== null" :described-by="$error('phone') ? 'enquiry-phone-error' : null" />
        </x-form.field>
        <x-form.field label="Company" for="enquiry-company" :error="$error('company')">
            <x-form.input id="enquiry-company" name="company" autocomplete="organization" maxlength="120" :value="old('company')" :invalid="$error('company') !== null" />
        </x-form.field>
        <x-form.field label="Team size" for="enquiry-team-size" :error="$error('team_size')">
            <x-form.select id="enquiry-team-size" name="team_size" placeholder="Choose a range" :selected="old('team_size')" :options="$teamSizes" />
        </x-form.field>
        <x-form.field label="Which best describes you?" for="enquiry-segment" hint="Optional." :error="$error('segment')">
            <x-form.select id="enquiry-segment" name="segment" placeholder="Choose one" :selected="old('segment')" :options="$segments->pluck('name', 'id')->all()" described-by="enquiry-segment-hint" />
        </x-form.field>
    </div>

    @unless ($isWaitlist)
        <x-form.field label="Your message" for="enquiry-message" :error="$error('message')">
            <x-form.textarea id="enquiry-message" name="message" maxlength="2000" placeholder="Move-in date, number of seats, anything else" :invalid="$error('message') !== null">{{ old('message') }}</x-form.textarea>
        </x-form.field>
    @endunless

    <div class="flex flex-col gap-1.5">
        <x-form.checkbox id="enquiry-consent" name="consent" value="1" :checked="(bool) old('consent')"
            :label="$isWaitlist ? 'SmartGro may contact me by email or phone if it opens in '.$cityName.'.' : \App\Services\Website\EnquiryForm::CONSENT_TEXT" />
        <p class="pl-8 text-sm text-muted">
            How SmartGro uses your details: <a href="/{{ $notice->slug }}" class="font-semibold text-leaf-700 hover:underline">{{ $notice->title }}</a>.
        </p>
        @if ($error('consent'))
            <p id="enquiry-consent-error" class="flex items-center gap-1.5 pl-8 text-sm font-medium text-danger">
                <x-heroicon-m-exclamation-circle class="size-4 shrink-0" aria-hidden="true" />{{ $error('consent') }}
            </p>
        @endif
    </div>

    <div class="flex flex-col gap-3 border-t border-line pt-5 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-muted">{{ $isWaitlist ? 'This registers your interest. It does not reserve a workspace.' : 'This sends an enquiry. Nothing is reserved, booked or charged.' }}</p>
        <x-ui.button type="submit" size="lg">{{ $isWaitlist ? 'Register interest' : 'Send enquiry' }}</x-ui.button>
    </div>
</form>
