@props([
    'properties' => [],
    'selected' => null,
    'slotState' => 'unconfigured',
    'idPrefix' => 'tour',
])

{{--
    Tour request (IA §3.1 item 12). Until tour hours are configured for a property (BR-TR-1, D-28),
    visitors give a preferred date and part of the day and staff confirm a time. Submitting creates a
    request, never a confirmed tour (BR-TR-2, BR-TR-3).
--}}
<form action="#" {{ $attributes->class(['flex flex-col gap-6']) }}>
    @if (count($properties) > 1)
        <x-form.radio-cards
            :name="$idPrefix.'-property'"
            legend="Which property would you like to visit?"
            :checked="$selected"
            :options="collect($properties)->mapWithKeys(fn ($property) => [$property['slug'] => ['title' => $property['name'], 'description' => $property['area'].', '.$property['city']]])->all()"
        />
    @endif

    <x-form.field label="Preferred date" :for="$idPrefix.'-date'" required>
        <x-form.input :id="$idPrefix.'-date'" type="date" />
    </x-form.field>

    @if ($slotState === 'unconfigured')
        <x-ui.slot-picker :name="$idPrefix.'-time'" legend="Preferred time" state="unconfigured" />
        <x-form.radio-cards
            :name="$idPrefix.'-part-of-day'"
            legend="Part of the day"
            :columns="3"
            :options="['morning' => ['title' => 'Morning'], 'afternoon' => ['title' => 'Afternoon'], 'evening' => ['title' => 'Evening']]"
        />
    @else
        <x-ui.slot-picker :name="$idPrefix.'-time'" legend="Choose a time" :state="$slotState" caption="Sample times for layout only: real times come from each property's tour hours." :slots="[
            ['value' => '1', 'label' => '10:00'], ['value' => '2', 'label' => '10:30', 'disabled' => true], ['value' => '3', 'label' => '11:00', 'checked' => true], ['value' => '4', 'label' => '11:30'],
            ['value' => '5', 'label' => '14:00'], ['value' => '6', 'label' => '14:30'], ['value' => '7', 'label' => '15:00', 'disabled' => true], ['value' => '8', 'label' => '15:30'],
        ]" />
    @endif

    <div class="grid gap-4 sm:grid-cols-2">
        <x-form.field label="Full name" :for="$idPrefix.'-name'" required>
            <x-form.input :id="$idPrefix.'-name'" autocomplete="name" />
        </x-form.field>
        <x-form.field label="Mobile number" :for="$idPrefix.'-phone'" required>
            <x-form.input :id="$idPrefix.'-phone'" type="tel" autocomplete="tel" inputmode="tel" />
        </x-form.field>
        <x-form.field label="Work email" :for="$idPrefix.'-email'" required>
            <x-form.input :id="$idPrefix.'-email'" type="email" autocomplete="email" />
        </x-form.field>
        <x-form.field label="Team size" :for="$idPrefix.'-size'">
            <x-form.select :id="$idPrefix.'-size'" placeholder="Choose a range" :options="['1' => 'Just me', '2-5' => '2–5 people', '6-20' => '6–20 people', '21-49' => '21–49 people', '50+' => '50 or more']" />
        </x-form.field>
    </div>

    <x-form.checkbox :id="$idPrefix.'-consent'" label="Contact me about this tour request" hint="SmartGro uses your details only for this request. Privacy notice to be added (D-15)." />

    <div class="flex flex-col gap-3 border-t border-line pt-5 sm:flex-row sm:items-center sm:justify-between">
        <p class="text-sm text-muted">This sends a request. A SmartGro team member confirms the time with you.</p>
        <x-ui.button type="submit" size="lg" icon="arrow-right">Request a tour</x-ui.button>
    </div>
</form>
