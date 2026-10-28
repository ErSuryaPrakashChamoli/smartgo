<?php

namespace Database\Factories;

use App\Enums\Crm\LeadStatus;
use App\Models\Contact;
use App\Models\Lead;
use App\Models\Property;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Lead>
 */
class LeadFactory extends Factory
{
    /**
     * A new, unassigned website enquiry without a property, as the form leaves it.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'reference' => 'ENQ-'.fake()->unique()->regexify('[A-HJ-NP-Z2-9]{8}'),
            'contact_id' => Contact::factory(),
            'source' => Lead::SOURCE_WEBSITE_FORM,
            'status' => LeadStatus::New,
            'submitted_name' => fake()->name(),
            'submitted_email' => fake()->safeEmail(),
            'submitted_phone' => null,
            'message' => fake()->sentence(),
            'consent_snapshot' => ['purpose' => 'service_communication', 'text' => 'Test consent', 'notice_path' => '/legal/privacy', 'notice_revision' => 1, 'marketing' => false],
        ];
    }

    /**
     * About a property.
     */
    public function forProperty(Property $property): static
    {
        return $this->state(['property_id' => $property->getKey(), 'city_id' => $property->city_id]);
    }
}
