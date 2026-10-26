<?php

namespace App\Models;

use App\Enums\Access\Capability;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\PublishesRevisionFields;
use App\Models\Contracts\Unpublishable;
use App\Services\Content\BlockLibrary;
use App\Services\Content\ContentRules;
use Database\Factories\CaseStudyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A customer case study (FR-CMS-7) at /customers/{slug}. A version is submitted and published only
 * with the customer's consent on file (BR-CMS-7), and is published with `testimonials.publish`.
 */
class CaseStudy extends Model implements Unpublishable
{
    /** @use HasFactory<CaseStudyFactory> */
    use HasFactory, HasRevisions, PublishesRevisionFields;

    /**
     * @var list<string>
     */
    public const array BLOCK_TYPES = ['text', 'image', 'features', 'stats', 'comparison', 'faq', 'cta', 'testimonial'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['blocks' => 'array', 'consent_on_file' => 'boolean', 'published_at' => 'datetime'];
    }

    public function path(): ?string
    {
        return $this->isPublished() ? '/customers/'.$this->slug : null;
    }

    public function contentLabel(): string
    {
        return 'customer story';
    }

    public function revisionCapabilities(): array
    {
        return ['view' => Capability::ViewContent, 'edit' => Capability::EditContent, 'approve' => Capability::ApproveContent, 'publish' => Capability::PublishTestimonials];
    }

    protected function revisionFields(): array
    {
        return ['title', 'slug', 'company_name', 'summary', 'blocks', 'consent_on_file', 'consent_reference'];
    }

    protected function payloadForValidation(array $payload): array
    {
        return [...$payload, 'blocks' => is_array($payload['blocks'] ?? null) ? array_values($payload['blocks']) : []];
    }

    protected function revisionRules(array $payload, bool $forSubmission): array
    {
        $rules = app(ContentRules::class);
        ['rules' => $blockRules, 'attributes' => $attributes] = app(BlockLibrary::class)->rules($payload['blocks'], $forSubmission, self::BLOCK_TYPES);

        return [
            'rules' => [
                'title' => $rules->plainText($forSubmission, 120),
                'slug' => $rules->slug($forSubmission, 'case_studies', $this->getKey()),
                'company_name' => $rules->plainText($forSubmission, 120),
                'summary' => $rules->plainText($forSubmission, 300),
                'consent_on_file' => $forSubmission ? ['accepted'] : ['nullable', 'boolean'],
                'consent_reference' => $rules->plainText($forSubmission, 120),
                ...$blockRules,
            ],
            'messages' => $rules->messages(),
            'attributes' => $attributes,
        ];
    }

    protected function normalizeRevisionPayload(array $payload): array
    {
        return [...$payload, 'consent_on_file' => (bool) $payload['consent_on_file'], 'blocks' => app(BlockLibrary::class)->normalize($payload['blocks'] ?? [])];
    }
}
