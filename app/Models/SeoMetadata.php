<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * Live search-engine metadata of published content (FR-SEO-1). It is written only when a revision is
 * published, so it never describes a draft.
 */
#[Fillable(['meta_title', 'meta_description', 'robots', 'og_asset_id'])]
class SeoMetadata extends Model
{
    protected $table = 'seo_metadata';

    public const string INDEX = 'index, follow';

    public const string NOINDEX = 'noindex, follow';

    /**
     * @return MorphTo<Model, $this>
     */
    public function seoable(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<MediaAsset, $this>
     */
    public function shareImage(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'og_asset_id');
    }

    public function isIndexable(): bool
    {
        return $this->robots !== self::NOINDEX;
    }
}
