<?php

namespace App\Models;

use App\Enums\Access\Capability;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\PublishesRevisionFields;
use App\Models\Contracts\Unpublishable;
use App\Services\Content\ContentRules;
use Database\Factories\TestimonialFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A customer testimonial (FR-CMS-7), shown through the testimonial block. A version is submitted and
 * published only with consent on file and a verified, unedited statement (BR-CMS-7), and is published
 * with `testimonials.publish`. Nothing here may be invented.
 */
class Testimonial extends Model implements Unpublishable
{
    /** @use HasFactory<TestimonialFactory> */
    use HasFactory, HasRevisions, PublishesRevisionFields;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['consent_on_file' => 'boolean', 'statement_verified' => 'boolean', 'published_at' => 'datetime'];
    }

    public function contentLabel(): string
    {
        return 'testimonial';
    }

    public function revisionCapabilities(): array
    {
        return ['view' => Capability::ViewContent, 'edit' => Capability::EditContent, 'approve' => Capability::ApproveContent, 'publish' => Capability::PublishTestimonials];
    }

    /**
     * Only a published testimonial with consent on file may appear on the website.
     */
    public function isShowable(): bool
    {
        return $this->isPublished() && $this->consent_on_file && $this->statement_verified;
    }

    protected function revisionFields(): array
    {
        return ['quote', 'person_name', 'person_title', 'company_name', 'consent_on_file', 'consent_reference', 'statement_verified'];
    }

    protected function revisionRules(array $payload, bool $forSubmission): array
    {
        $rules = app(ContentRules::class);

        return [
            'rules' => [
                'quote' => $rules->plainText($forSubmission, 600),
                'person_name' => $rules->plainText($forSubmission, 80),
                'person_title' => $rules->plainText(false, 80),
                'company_name' => $rules->plainText(false, 120),
                'consent_on_file' => $forSubmission ? ['accepted'] : ['nullable', 'boolean'],
                'consent_reference' => $rules->plainText($forSubmission, 120),
                'statement_verified' => $forSubmission ? ['accepted'] : ['nullable', 'boolean'],
            ],
            'messages' => $rules->messages(),
        ];
    }

    protected function normalizeRevisionPayload(array $payload): array
    {
        return [...$payload, 'consent_on_file' => (bool) $payload['consent_on_file'], 'statement_verified' => (bool) $payload['statement_verified']];
    }
}
