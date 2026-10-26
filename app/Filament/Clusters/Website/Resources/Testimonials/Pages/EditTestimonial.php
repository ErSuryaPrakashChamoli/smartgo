<?php

namespace App\Filament\Clusters\Website\Resources\Testimonials\Pages;

use App\Filament\Clusters\Website\Concerns\HasRevisionWorkflow;
use App\Filament\Clusters\Website\Resources\Testimonials\TestimonialResource;
use App\Models\Testimonial;
use Filament\Resources\Pages\EditRecord;

/**
 * The testimonial editor; the workflow lives in HasRevisionWorkflow.
 *
 * @property Testimonial $record
 */
class EditTestimonial extends EditRecord
{
    use HasRevisionWorkflow;

    protected static string $resource = TestimonialResource::class;

    public function getTitle(): string
    {
        return TestimonialResource::getRecordTitle($this->record);
    }

    public function getSubheading(): ?string
    {
        return $this->record->isPublished()
            ? 'Live: shown wherever a page, article or customer story includes it'
            : 'Not published yet';
    }

    protected function getHeaderActions(): array
    {
        return $this->workflowHeaderActions();
    }

    protected function contentNoun(): string
    {
        return 'testimonial';
    }

    protected function formDataFromPayload(array $payload): array
    {
        return [
            'quote' => $payload['quote'] ?? null,
            'person_name' => $payload['person_name'] ?? null,
            'person_title' => $payload['person_title'] ?? null,
            'company_name' => $payload['company_name'] ?? null,
            'consent_on_file' => (bool) ($payload['consent_on_file'] ?? false),
            'consent_reference' => $payload['consent_reference'] ?? null,
            'statement_verified' => (bool) ($payload['statement_verified'] ?? false),
        ];
    }
}
