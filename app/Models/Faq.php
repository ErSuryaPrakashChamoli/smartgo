<?php

namespace App\Models;

use App\Enums\Access\Capability;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\PublishesRevisionFields;
use App\Models\Contracts\Revisionable;
use App\Services\Content\ContentRules;
use Database\Factories\FaqFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * A frequently asked question (FR-CMS-7), listed at /faqs in `sort_order`. Property and product
 * scopes arrive with Phase 5.
 */
class Faq extends Model implements Revisionable
{
    /** @use HasFactory<FaqFactory> */
    use HasFactory, HasRevisions, PublishesRevisionFields;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort_order' => 'integer', 'published_at' => 'datetime'];
    }

    public function revisionCapabilities(): array
    {
        return ['view' => Capability::ViewContent, 'edit' => Capability::EditContent, 'approve' => Capability::ApproveContent, 'publish' => Capability::PublishContent];
    }

    protected function revisionFields(): array
    {
        return ['question', 'answer', 'sort_order'];
    }

    protected function revisionRules(array $payload, bool $forSubmission): array
    {
        $rules = app(ContentRules::class);

        return [
            'rules' => [
                'question' => $rules->plainText($forSubmission, 150),
                'answer' => $rules->plainText($forSubmission, 1500),
                'sort_order' => ['nullable', 'integer', 'between:0,9999'],
            ],
            'messages' => $rules->messages(),
        ];
    }

    protected function normalizeRevisionPayload(array $payload): array
    {
        return [...$payload, 'sort_order' => (int) ($payload['sort_order'] ?? 0)];
    }
}
