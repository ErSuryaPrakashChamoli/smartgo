<?php

namespace App\Models;

use App\Enums\Access\Capability;
use App\Models\Concerns\HasRevisions;
use App\Models\Concerns\PublishesRevisionFields;
use App\Models\Contracts\Unpublishable;
use App\Services\Content\ContentLink;
use App\Services\Content\ContentRules;
use Closure;
use Database\Factories\HomeBannerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

/**
 * One slide of the home page's banner carousel (OD-169), in `sort_order`: a heading, a line of text, an image from
 * the media library and up to two buttons. Its versions are approved and published by different people with the
 * website-content permissions, and it can be taken off the website at once (OD-98). The image keeps the label its
 * origin needs (BR-MED-2), and one whose licence has expired is refused and not shown (BR-MED-3). A database not
 * migrated for banners keeps the single home hero (isAvailable()).
 */
class HomeBanner extends Model implements Unpublishable
{
    /** @use HasFactory<HomeBannerFactory> */
    use HasFactory, HasRevisions, PublishesRevisionFields;

    /**
     * The carousel shows at most this many banners, the first by position.
     */
    public const int MAX_SHOWN = 6;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'media_asset_id' => 'integer',
            'primary_link' => 'array',
            'secondary_link' => 'array',
            'sort_order' => 'integer',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Whether this database has banners. Asked once a request.
     */
    public static function isAvailable(): bool
    {
        return once(fn (): bool => Schema::hasTable((new self)->getTable()));
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function mediaAsset(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class);
    }

    public function contentLabel(): string
    {
        return 'home banner';
    }

    public function revisionCapabilities(): array
    {
        return ['view' => Capability::ViewContent, 'edit' => Capability::EditContent, 'approve' => Capability::ApproveContent, 'publish' => Capability::PublishContent];
    }

    protected function revisionFields(): array
    {
        return ['heading', 'text', 'media_asset_id', 'image_alt', 'primary_link', 'secondary_link', 'sort_order'];
    }

    protected function revisionRules(array $payload, bool $forSubmission): array
    {
        $rules = app(ContentRules::class);
        $contentLink = app(ContentLink::class);

        return [
            'rules' => [
                'heading' => $rules->plainText($forSubmission, 120),
                'text' => $rules->plainText(false, 300),
                'media_asset_id' => [$forSubmission ? 'required' : 'nullable', 'integer', Rule::exists('media_assets', 'id'), $this->licenceRule()],
                'image_alt' => $rules->plainText(false, 150),
                ...$contentLink->rules('primary_link', required: false, forSubmission: $forSubmission),
                ...$contentLink->rules('secondary_link', required: false, forSubmission: $forSubmission),
                'sort_order' => ['nullable', 'integer', 'between:0,9999'],
            ],
            'messages' => [
                ...$rules->messages(),
                'media_asset_id.required' => 'Choose the banner\'s image from the media library.',
                'media_asset_id.exists' => 'The chosen image is no longer in the media library.',
            ],
            'attributes' => ['media_asset_id' => 'image', 'image_alt' => 'alt text'],
        ];
    }

    protected function normalizeRevisionPayload(array $payload): array
    {
        return [
            ...$payload,
            'media_asset_id' => filled($payload['media_asset_id'] ?? null) ? (int) $payload['media_asset_id'] : null,
            'primary_link' => $this->normalizedLink($payload['primary_link'] ?? null),
            'secondary_link' => $this->normalizedLink($payload['secondary_link'] ?? null),
            'sort_order' => (int) ($payload['sort_order'] ?? 0),
        ];
    }

    /**
     * A button as stored: every field present, or null when no button text was given.
     *
     * @param  array<string, mixed>|null  $link
     * @return array{label: string, type: string|null, page_id: int|null, section: string|null, product_id: int|null, url: string|null}|null
     */
    private function normalizedLink(?array $link): ?array
    {
        if (blank($link['label'] ?? null) && blank($link['type'] ?? null)) {
            return null;
        }

        return [
            'label' => (string) ($link['label'] ?? ''),
            'type' => $link['type'] ?? null,
            'page_id' => filled($link['page_id'] ?? null) ? (int) $link['page_id'] : null,
            'section' => $link['section'] ?? null,
            'product_id' => filled($link['product_id'] ?? null) ? (int) $link['product_id'] : null,
            'url' => $link['url'] ?? null,
        ];
    }

    private function licenceRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (is_numeric($value) && MediaAsset::query()->find((int) $value)?->isLicenceExpired()) {
                $fail('This image\'s licence has expired, so it cannot be used.');
            }
        };
    }
}
