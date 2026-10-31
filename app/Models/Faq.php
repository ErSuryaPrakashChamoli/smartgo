<?php

namespace App\Models;

use App\Enums\Access\Capability;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\PublishesRevisionFields;
use App\Models\Contracts\Revisionable;
use App\Services\Content\ContentRules;
use Database\Factories\FaqFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * A frequently asked question (FR-CMS-7), in `sort_order`. Its scope is part of its versions: a general
 * question is listed at /faqs; one about a location (`property_id`) is shown on that location's page,
 * with the general ones after it (IA §3.1); one about a workspace type (`product_id`) on that type's
 * page. Never both. A database not migrated for scopes keeps every question general (OD-120).
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

    /**
     * Whether this database has FAQ scopes. Asked once a request.
     */
    public static function hasScopes(): bool
    {
        return once(fn (): bool => Schema::hasColumns((new self)->getTable(), ['property_id', 'product_id']));
    }

    /**
     * @return BelongsTo<Property, $this>
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * Questions for the FAQ page: those about no location or workspace type.
     *
     * @param  Builder<self>  $query
     */
    public function scopeGeneral(Builder $query): void
    {
        if (self::hasScopes()) {
            $query->whereNull('property_id')->whereNull('product_id');
        }
    }

    /**
     * Questions about one location; none without scopes.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAboutProperty(Builder $query, Property $property): void
    {
        self::hasScopes() ? $query->whereBelongsTo($property) : $query->whereRaw('1 = 0');
    }

    /**
     * Questions about one workspace type; none without scopes.
     *
     * @param  Builder<self>  $query
     */
    public function scopeAboutProduct(Builder $query, Product $product): void
    {
        self::hasScopes() ? $query->whereBelongsTo($product) : $query->whereRaw('1 = 0');
    }

    public function revisionCapabilities(): array
    {
        return ['view' => Capability::ViewContent, 'edit' => Capability::EditContent, 'approve' => Capability::ApproveContent, 'publish' => Capability::PublishContent];
    }

    protected function revisionFields(): array
    {
        return ['question', 'answer', 'sort_order', ...(self::hasScopes() ? ['property_id', 'product_id'] : [])];
    }

    protected function revisionRules(array $payload, bool $forSubmission): array
    {
        $rules = app(ContentRules::class);

        return [
            'rules' => [
                'question' => $rules->plainText($forSubmission, 150),
                'answer' => $rules->plainText($forSubmission, 1500),
                'sort_order' => ['nullable', 'integer', 'between:0,9999'],
                ...(self::hasScopes() ? [
                    'property_id' => ['nullable', 'integer', Rule::exists('properties', 'id'), 'prohibits:product_id'],
                    'product_id' => ['nullable', 'integer', Rule::exists('products', 'id')],
                ] : []),
            ],
            'messages' => [
                ...$rules->messages(),
                'property_id.prohibits' => 'A question is about one location or one workspace type, not both.',
                'property_id.exists' => 'The chosen location no longer exists.',
                'product_id.exists' => 'The chosen workspace type no longer exists.',
            ],
        ];
    }

    protected function normalizeRevisionPayload(array $payload): array
    {
        return [
            ...$payload,
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
            ...(self::hasScopes() ? [
                'property_id' => filled($payload['property_id'] ?? null) ? (int) $payload['property_id'] : null,
                'product_id' => filled($payload['product_id'] ?? null) ? (int) $payload['product_id'] : null,
            ] : []),
        ];
    }
}
