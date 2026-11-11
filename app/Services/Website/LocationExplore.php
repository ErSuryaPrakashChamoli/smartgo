<?php

namespace App\Services\Website;

use App\Http\Controllers\Backoffice\TwinModelFileController;
use App\Models\MediaAsset;
use App\Models\Property;
use App\Models\PropertyProfile;
use App\Models\TwinAnchor;
use App\Models\TwinModel;
use App\Services\Twin\TwinViewerAssets;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * A location page's "Explore" section (OD-181, IA §3.1 step 4): the 3D walkthrough and the floor plans its website
 * profile holds, as the page shows them. Everything is checked again here, so a model or plan that no longer
 * qualifies is left out instead of shown:
 *
 * - the model must be one of this property's, with its file present. It is shown with the heading and notice its
 *   capture type requires (BR-3D-1, BR-3D-2), and nothing of it loads until the visitor asks (BR-3D-6). Its
 *   guided views are the profile's, at camera positions the model still has; without any, each camera position
 *   is a numbered view. The file is public only while a published profile of a listed location uses it.
 * - a floor plan must be SmartGro's own or a commissioned plan of this property, or, marked as an example, a
 *   render or concept, which the page labels; its licence must be valid and its file present.
 *
 * Nothing is read where the database has no such columns (PropertyProfile::hasExplore()).
 */
class LocationExplore
{
    public const string EXAMPLE_FLOOR_PLAN_LABEL = 'Example floor plan — not SmartGro\'s layout';

    public function __construct(private TwinViewerAssets $viewerAssets) {}

    /**
     * The section for a profile version, or null when it has nothing to show. In a staff preview the model comes
     * from the back office's file route, since a version not yet published is not public.
     *
     * @param  array<string, mixed>|null  $payload  the profile version shown
     * @return array{walkthrough: array<string, mixed>|null, floorPlans: list<array<string, mixed>>}|null
     */
    public function present(Property $property, ?array $payload, bool $isPreview = false): ?array
    {
        if ($payload === null || ! PropertyProfile::hasExplore()) {
            return null;
        }

        $walkthrough = $this->walkthrough($property, $payload, $isPreview);
        $floorPlans = $this->floorPlans($property, $payload['floor_plans'] ?? []);

        return $walkthrough === null && $floorPlans === [] ? null : ['walkthrough' => $walkthrough, 'floorPlans' => $floorPlans];
    }

    /**
     * What the live profile of a property holds, or null while it is not published.
     *
     * @return array<string, mixed>|null
     */
    public function livePayload(Property $property): ?array
    {
        if (! PropertyProfile::hasExplore()) {
            return null;
        }

        $profile = $property->profile;

        return $profile?->isPublished() ? $profile->only(PropertyProfile::EXPLORE_FIELDS) : null;
    }

    /**
     * Whether a published profile of a listed location shows the model, which makes its file public.
     */
    public function isPubliclyShown(TwinModel $model): bool
    {
        return PropertyProfile::hasExplore()
            && PropertyProfile::query()->published()
                ->where('twin_model_id', $model->getKey())
                ->where('property_id', $model->property_id)
                ->whereHas('property', fn (Builder $query) => $query->publiclyListed())
                ->exists();
    }

    /**
     * The first of these listed locations whose page shows a walkthrough or floor plans, for the home page, with the
     * number of locations that do.
     *
     * @param  Collection<int, Property>  $properties  listed properties, in the order the website lists them
     * @return array{name: string, href: string, walkthrough: bool, floorPlans: bool, locations: int}|null
     */
    public function showcase(Collection $properties): ?array
    {
        if (! PropertyProfile::hasExplore() || $properties->isEmpty()) {
            return null;
        }

        $profiles = PropertyProfile::query()->published()
            ->whereIn('property_id', $properties->modelKeys())
            ->where(fn (Builder $query) => $query->whereNotNull('twin_model_id')->orWhereJsonLength('floor_plans', '>', 0))
            ->get(['id', 'property_id', ...PropertyProfile::EXPLORE_FIELDS])
            ->keyBy('property_id');

        $shown = $properties
            ->filter(fn (Property $property): bool => $profiles->has($property->getKey()))
            ->map(fn (Property $property): array => [$property, $this->present($property, $profiles->get($property->getKey())->only(PropertyProfile::EXPLORE_FIELDS))])
            ->filter(fn (array $entry): bool => $entry[1] !== null)
            ->values();

        if ($shown->isEmpty()) {
            return null;
        }

        [$property, $explore] = $shown->first();

        return [
            'name' => 'SmartGro '.$property->name,
            'href' => $property->path().'#explore',
            'walkthrough' => $explore['walkthrough'] !== null,
            'floorPlans' => $explore['floorPlans'] !== [],
            'locations' => $shown->count(),
        ];
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function walkthrough(Property $property, array $payload, bool $isPreview): ?array
    {
        $id = $payload['twin_model_id'] ?? null;

        if (! is_numeric($id)) {
            return null;
        }

        $model = TwinModel::query()->with(['media', 'cameraAnchors'])->where('property_id', $property->getKey())->find((int) $id);
        $path = $model?->file()?->getPath();

        if ($model === null || $path === null || ! is_file($path)) {
            return null;
        }

        $cameras = $model->cameraAnchors->map(fn (TwinAnchor $anchor): string => $anchor->node_name)->all();
        $stops = collect($payload['twin_tour_stops'] ?? [])
            ->filter(fn (mixed $stop): bool => is_array($stop) && in_array($stop['camera'] ?? null, $cameras, true) && filled($stop['title'] ?? null))
            ->map(fn (array $stop): array => ['node' => (string) $stop['camera'], 'title' => (string) $stop['title']])
            ->values()->all();

        if ($stops === []) {
            $stops = array_map(fn (string $node, int $index): array => ['node' => $node, 'title' => 'View '.($index + 1)], $cameras, array_keys($cameras));
        }

        $location = 'SmartGro '.$property->name;
        $notice = $model->capture_type->disclaimer();

        return [
            ...$this->viewerAssets->forModel($model),
            'url' => $isPreview
                ? route(TwinModelFileController::ROUTE, ['twinModel' => $model], absolute: false)
                : route('twin-models.show', ['twinModel' => $model], absolute: false),
            'heading' => $model->capture_type->title($location),
            'notice' => $notice,
            'title' => $model->title,
            'label' => $model->capture_type->title($location).($notice !== null ? '. '.$notice : ''),
            'stops' => $stops,
        ];
    }

    /**
     * @return list<array{label: string, note: string|null, isExample: bool, src: string, srcset: string, alt: string, credit: string|null, dimensions: array{width: int, height: int}|null}>
     */
    private function floorPlans(Property $property, mixed $plans): array
    {
        if (! is_array($plans) || $plans === []) {
            return [];
        }

        $plans = array_values(array_filter($plans, fn (mixed $plan): bool => is_array($plan) && is_numeric($plan['media_asset_id'] ?? null) && filled($plan['label'] ?? null)));
        $assets = MediaAsset::query()->with('media')->whereKey(array_map(fn (array $plan): int => (int) $plan['media_asset_id'], $plans))->get()->keyBy('id');

        return collect($plans)
            ->map(function (array $plan) use ($assets, $property): ?array {
                $asset = $assets->get((int) $plan['media_asset_id']);
                $isExample = (bool) ($plan['is_example'] ?? false);

                if ($asset === null || ! $this->isShowableFloorPlan($asset, $property, $isExample)) {
                    return null;
                }

                return [
                    'label' => (string) $plan['label'],
                    'note' => filled($plan['note'] ?? null) ? (string) $plan['note'] : null,
                    'isExample' => $isExample,
                    'src' => $asset->renditionPath('w960'),
                    'srcset' => collect(MediaAsset::RENDITIONS)->map(fn (int $width, string $name): string => $asset->renditionPath($name).' '.$width.'w')->join(', '),
                    'alt' => $asset->is_decorative ? '' : (string) $asset->alt_text,
                    'credit' => $asset->credit_line,
                    'dimensions' => $asset->dimensions(),
                ];
            })
            ->filter(fn (?array $plan): bool => $plan !== null)
            ->values()->all();
    }

    private function isShowableFloorPlan(MediaAsset $asset, Property $property, bool $isExample): bool
    {
        $originAllowed = $isExample
            ? in_array($asset->origin, PropertyProfile::EXAMPLE_FLOOR_PLAN_ORIGINS, true)
            : in_array($asset->origin, PropertyProfile::FLOOR_PLAN_ORIGINS, true) && (int) $asset->depicts_property_id === (int) $property->getKey();

        return $originAllowed && ! $asset->isLicenceExpired() && $asset->file() !== null;
    }
}
