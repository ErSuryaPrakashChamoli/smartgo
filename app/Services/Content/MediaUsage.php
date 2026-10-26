<?php

namespace App\Services\Content;

use App\Models\MediaAsset;
use App\Models\Page;
use App\Models\Revision;
use App\Models\SeoMetadata;

/**
 * Where an asset is used. Image blocks store `media_asset_id` in their data and share images are
 * `seo.image_asset_id` in a page payload (`og_asset_id` once live).
 */
class MediaUsage
{
    /**
     * Whether published content shows the asset, which makes its renditions public.
     */
    public function isPubliclyUsed(MediaAsset $asset): bool
    {
        return Page::query()->published()->whereRaw('JSON_CONTAINS(blocks, ?)', [$this->blockNeedle($asset)])->exists()
            || SeoMetadata::query()->where('og_asset_id', $asset->getKey())
                ->whereHasMorph('seoable', [Page::class], fn ($query) => $query->where('status', 'published'))
                ->exists();
    }

    /**
     * Whether any live content or any version, past or in progress, uses the asset. Such an asset is
     * not deleted, so history and restores keep working.
     */
    public function isReferenced(MediaAsset $asset): bool
    {
        return Page::query()->whereRaw('JSON_CONTAINS(blocks, ?)', [$this->blockNeedle($asset)])->exists()
            || SeoMetadata::query()->where('og_asset_id', $asset->getKey())->exists()
            || Revision::query()->where(fn ($query) => $query
                ->whereRaw('JSON_CONTAINS(payload, ?)', [json_encode(['blocks' => [['data' => ['media_asset_id' => $asset->getKey()]]]])])
                ->orWhereRaw('JSON_CONTAINS(payload, ?)', [json_encode(['seo' => ['image_asset_id' => $asset->getKey()]])]))
                ->exists();
    }

    private function blockNeedle(MediaAsset $asset): string
    {
        return (string) json_encode(['data' => ['media_asset_id' => $asset->getKey()]]);
    }
}
