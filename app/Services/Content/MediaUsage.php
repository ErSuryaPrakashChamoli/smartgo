<?php

namespace App\Services\Content;

use App\Models\CaseStudy;
use App\Models\MediaAsset;
use App\Models\OfferingProfile;
use App\Models\Page;
use App\Models\Post;
use App\Models\PropertyProfile;
use App\Models\Revision;
use App\Models\SeoMetadata;
use Illuminate\Database\Eloquent\Builder;

/**
 * Where an asset is used. Image blocks (in pages, posts and case studies) store `media_asset_id` in
 * their data, share images are `seo.image_asset_id` in a page payload (`og_asset_id` once live), and
 * property and offering profiles hold `hero_asset_id` and `gallery_asset_ids`. A profile's photos are
 * public only while that profile is on the website.
 */
class MediaUsage
{
    /**
     * Whether published content shows the asset, which makes its renditions public.
     */
    public function isPubliclyUsed(MediaAsset $asset): bool
    {
        return Page::query()->published()->whereRaw('JSON_CONTAINS(blocks, ?)', [$this->blockNeedle($asset)])->exists()
            || Post::query()->published()->whereRaw('JSON_CONTAINS(blocks, ?)', [$this->blockNeedle($asset)])->exists()
            || CaseStudy::query()->published()->whereRaw('JSON_CONTAINS(blocks, ?)', [$this->blockNeedle($asset)])->exists()
            || SeoMetadata::query()->where('og_asset_id', $asset->getKey())
                ->whereHasMorph('seoable', [Page::class], fn ($query) => $query->where('status', 'published'))
                ->exists()
            || $this->profilesShowing(PropertyProfile::query(), $asset)->published()->whereHas('property', fn ($query) => $query->publiclyListed())->exists()
            || $this->profilesShowing(OfferingProfile::query(), $asset)->published()->whereHas('offering', fn ($query) => $query->publiclyListed())->exists();
    }

    /**
     * Whether any live content or any version, past or in progress, uses the asset. Such an asset is
     * not deleted, so history and restores keep working.
     */
    public function isReferenced(MediaAsset $asset): bool
    {
        return Page::query()->whereRaw('JSON_CONTAINS(blocks, ?)', [$this->blockNeedle($asset)])->exists()
            || Post::query()->whereRaw('JSON_CONTAINS(blocks, ?)', [$this->blockNeedle($asset)])->exists()
            || CaseStudy::query()->whereRaw('JSON_CONTAINS(blocks, ?)', [$this->blockNeedle($asset)])->exists()
            || SeoMetadata::query()->where('og_asset_id', $asset->getKey())->exists()
            || $this->profilesShowing(PropertyProfile::query(), $asset)->exists()
            || $this->profilesShowing(OfferingProfile::query(), $asset)->exists()
            || Revision::query()->where(fn ($query) => $query
                ->whereRaw('JSON_CONTAINS(payload, ?)', [json_encode(['blocks' => [['data' => ['media_asset_id' => $asset->getKey()]]]])])
                ->orWhereRaw('JSON_CONTAINS(payload, ?)', [json_encode(['seo' => ['image_asset_id' => $asset->getKey()]])])
                ->orWhereRaw('JSON_CONTAINS(payload, ?)', [json_encode(['hero_asset_id' => $asset->getKey()])])
                ->orWhereRaw('JSON_CONTAINS(payload, ?)', [json_encode(['gallery_asset_ids' => [$asset->getKey()]])]))
                ->exists();
    }

    /**
     * Profiles whose live row uses the asset as the main photo or in the photos.
     *
     * @template TProfile of PropertyProfile|OfferingProfile
     *
     * @param  Builder<TProfile>  $profiles
     * @return Builder<TProfile>
     */
    private function profilesShowing(Builder $profiles, MediaAsset $asset): Builder
    {
        return $profiles->where(fn (Builder $query): Builder => $query
            ->where('hero_asset_id', $asset->getKey())
            ->orWhereRaw('JSON_CONTAINS(gallery_asset_ids, ?)', [json_encode($asset->getKey())]));
    }

    private function blockNeedle(MediaAsset $asset): string
    {
        return (string) json_encode(['data' => ['media_asset_id' => $asset->getKey()]]);
    }
}
