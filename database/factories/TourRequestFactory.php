<?php

namespace Database\Factories;

use App\Enums\Booking\TourStatus;
use App\Models\Lead;
use App\Models\Property;
use App\Models\TourRequest;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * A visitor's tour request from the website, waiting for staff.
 *
 * @extends Factory<TourRequest>
 */
class TourRequestFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'TOUR-'.fake()->unique()->regexify('[A-HJ-NP-Z2-9]{8}'),
            'lead_id' => Lead::factory(),
            'property_id' => Property::factory(),
            'scheduled_start_at' => now()->addDay()->startOfHour(),
            'duration_minutes' => 30,
            'status' => TourStatus::Requested,
            'channel' => TourRequest::CHANNEL_WEBSITE,
            'visitor_count' => 1,
        ];
    }

    /**
     * For a property, with an enquiry about it.
     */
    public function forProperty(Property $property): static
    {
        return $this->state(fn (): array => ['property_id' => $property->getKey(), 'lead_id' => Lead::factory()->forProperty($property)]);
    }

    public function confirmed(): static
    {
        return $this->state(['status' => TourStatus::Confirmed, 'confirmed_at' => now()]);
    }
}
