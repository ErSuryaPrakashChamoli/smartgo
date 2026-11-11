<?php

namespace App\Services\Website;

use App\Enums\Content\MediaOrigin;
use App\Models\HomeBanner;
use App\Models\HomeHighlight;
use App\Models\MediaAsset;
use App\Models\Property;
use App\Services\Content\ContentLink;
use Illuminate\Support\Collection;

/**
 * What the home page's banner carousel and highlights strip show (OD-169). Slides are the published banners, by
 * position, whose image is in the library with a valid licence (BR-MED-3); each image keeps the label its origin
 * needs (BR-MED-2). Buttons follow ContentLink: one whose page or part of the website is not there is left out.
 * Highlights are the published ones, by position; until one is published, or on a database without them, the strip
 * shows facts from the records instead: the workspace types, the listed locations and whether tours are offered.
 */
class HomeShowcase
{
    public function __construct(private ContentLink $contentLink) {}

    /**
     * @return list<array{heading: string, text: string|null, image: array{src: string, srcset: string, alt: string, dimensions: array{width: int, height: int}|null, label: string|null}, links: list<array{label: string, href: string, external: bool}>}>
     */
    public function slides(): array
    {
        if (! HomeBanner::isAvailable()) {
            return [];
        }

        return HomeBanner::query()
            ->published()
            ->with(['mediaAsset.media', 'mediaAsset.depictedProperty'])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->limit(HomeBanner::MAX_SHOWN)
            ->get()
            ->map(fn (HomeBanner $banner): ?array => $this->slide($banner->toRevisionPayload(), $banner->mediaAsset))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * One banner's slide from its fields (the live row's, or a version's for its preview); null without a showable image.
     *
     * @param  array<string, mixed>  $fields
     * @return array{heading: string, text: string|null, image: array{src: string, srcset: string, alt: string, dimensions: array{width: int, height: int}|null, label: string|null}, links: list<array{label: string, href: string, external: bool}>}|null
     */
    public function slide(array $fields, ?MediaAsset $asset = null): ?array
    {
        $asset ??= is_numeric($fields['media_asset_id'] ?? null) ? MediaAsset::query()->with(['media', 'depictedProperty'])->find((int) $fields['media_asset_id']) : null;

        if ($asset === null || $asset->isLicenceExpired() || $asset->file() === null) {
            return null;
        }

        return [
            'heading' => (string) ($fields['heading'] ?? ''),
            'text' => $fields['text'] ?? null,
            'image' => $this->image($asset, $fields['image_alt'] ?? null),
            'links' => array_values(array_filter([
                $this->contentLink->present($fields['primary_link'] ?? null),
                $this->contentLink->present($fields['secondary_link'] ?? null),
            ])),
        ];
    }

    /**
     * The strip's items, and whether any of them is example text.
     *
     * @param  list<array{name: string}>  $categories  the workspace types on the website (WorkspaceCatalogue::categories())
     * @param  Collection<int, Property>  $properties  the listed locations
     * @return array{items: list<array{icon: string, text: string}>, sample: bool}
     */
    public function highlights(array $categories, Collection $properties, bool $tourOffered): array
    {
        $published = HomeHighlight::isAvailable()
            ? HomeHighlight::query()->published()->orderBy('sort_order')->orderBy('id')->limit(HomeHighlight::MAX_SHOWN)->get()
            : collect();

        if ($published->isNotEmpty()) {
            return [
                'items' => $published->map(fn (HomeHighlight $highlight): array => [
                    'icon' => array_key_exists((string) $highlight->icon, HomeHighlight::ICONS) ? (string) $highlight->icon : HomeHighlight::DEFAULT_ICON,
                    'text' => (string) $highlight->text,
                ])->values()->all(),
                'sample' => $published->contains(fn (HomeHighlight $highlight): bool => $highlight->is_sample),
            ];
        }

        return [
            'items' => [
                ...array_map(fn (array $category): array => ['icon' => 'squares-2x2', 'text' => (string) $category['name']], $categories),
                ...$properties->map(fn (Property $property): array => ['icon' => 'map-pin', 'text' => 'SmartGro '.$property->name.($property->city?->name ? ', '.$property->city->name : '')])->values()->all(),
                ...($tourOffered ? [['icon' => 'calendar-days', 'text' => 'Tours on request']] : []),
            ],
            'sample' => false,
        ];
    }

    /**
     * @return array{src: string, srcset: string, alt: string, dimensions: array{width: int, height: int}|null, label: string|null}
     */
    private function image(MediaAsset $asset, ?string $alt): array
    {
        return [
            'src' => $asset->renditionPath('w960'),
            'srcset' => collect(MediaAsset::RENDITIONS)->map(fn (int $width, string $name): string => $asset->renditionPath($name).' '.$width.'w')->join(', '),
            'alt' => $asset->is_decorative && blank($alt) ? '' : (string) (filled($alt) ? $alt : $asset->alt_text),
            'dimensions' => $asset->dimensions(),
            'label' => $this->label($asset),
        ];
    }

    /**
     * What the image is, shown on it: never presented as a SmartGro location unless it is a photo of one.
     */
    private function label(MediaAsset $asset): ?string
    {
        return match ($asset->origin) {
            MediaOrigin::AiGeneratedConcept => 'AI-generated image, not a photo of a SmartGro location',
            MediaOrigin::Render => 'Concept visual, not a photo of a SmartGro location',
            MediaOrigin::LicensedStock => 'Stock photo, not a SmartGro location',
            default => $asset->depictedProperty?->isPubliclyListed() ? 'SmartGro '.$asset->depictedProperty->name : null,
        };
    }
}
