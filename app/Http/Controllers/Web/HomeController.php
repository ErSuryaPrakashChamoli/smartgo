<?php

namespace App\Http\Controllers\Web;

use App\Http\Controllers\Controller;
use App\Models\CustomerSegment;
use App\Models\Faq;
use App\Models\Offering;
use App\Models\Page;
use App\Models\Post;
use App\Models\Property;
use App\Services\Content\SiteNavigation;
use App\Services\Website\EnquiryForm;
use App\Services\Website\TourForm;
use App\Services\Website\WorkspaceCatalogue;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;

/**
 * The public home page: workspace categories, open properties, their published photos and contact
 * details, all from records. Nothing is shown about prices or availability until that data exists,
 * and links to the enquiry and tour forms appear only while those forms can be used. The customer
 * segments, newest articles and general questions follow (OD-143), each only while there is one, and a
 * segment links to its solutions page only while that page is published (BR-CMS-9).
 */
class HomeController extends Controller
{
    public const int LATEST_POSTS = 3;

    public const int FAQS_SHOWN = 4;

    public function __invoke(WorkspaceCatalogue $catalogue, SiteNavigation $siteNavigation, EnquiryForm $enquiryForm, TourForm $tourForm): View
    {
        $properties = $catalogue->listedProperties();
        $categories = $catalogue->categories($properties);
        $groups = $catalogue->groups($categories);
        $navigation = $siteNavigation->present();
        $photos = $catalogue->mainPhotos($properties);
        $offerings = $properties->flatMap(fn (Property $property) => $property->publishedOfferings);

        return view('pages.home', [
            'properties' => $properties,
            'cityNames' => $properties->pluck('city.name')->unique()->values()->all(),
            'categories' => $categories,
            'groups' => array_map(fn (array $group): array => [...$group, 'photo' => $this->groupPhoto($group, $offerings, $photos['offerings'])], $groups),
            'propertyPhotos' => $photos['properties'],
            'heroPhoto' => $this->heroPhoto($properties, $photos['properties']),
            'segments' => $this->segments(),
            'posts' => Post::query()->published()->latest('published_at')->limit(self::LATEST_POSTS)->get(),
            'faqs' => Faq::query()->published()->general()->orderBy('sort_order')->orderBy('id')->limit(self::FAQS_SHOWN)->get(),
            'enquiryOpen' => $enquiryForm->isOpen(),
            'tourOffered' => $tourForm->isOffered(),
            'contact' => $navigation['contact'],
            'navigation' => $navigation,
            'canonical' => rtrim((string) config('app.url'), '/').'/',
        ]);
    }

    /**
     * The offered customer segments (OD-04), each with the address of its published solutions page, if any.
     *
     * @return list<array{name: string, href: string|null}>
     */
    private function segments(): array
    {
        $segments = CustomerSegment::query()->offered()->get(['id', 'name', 'slug']);
        $published = Page::query()->published()->whereIn('slug', $segments->map(fn (CustomerSegment $segment): string => 'solutions/'.$segment->slug)->all())->pluck('slug')->all();

        return $segments->map(fn (CustomerSegment $segment): array => [
            'name' => (string) $segment->name,
            'href' => in_array('solutions/'.$segment->slug, $published, true) ? '/solutions/'.$segment->slug : null,
        ])->values()->all();
    }

    /**
     * The first listed property's published main photo, with the location it shows.
     *
     * @param  Collection<int, Property>  $properties
     * @param  array<int, array<string, mixed>>  $photos
     * @return array<string, mixed>|null
     */
    private function heroPhoto(Collection $properties, array $photos): ?array
    {
        $property = $properties->first(fn (Property $property): bool => isset($photos[$property->getKey()]));

        return $property === null ? null : [...$photos[$property->getKey()], 'caption' => 'SmartGro '.$property->name.', '.collect([$property->microMarket?->name, $property->city->name])->filter()->join(', ')];
    }

    /**
     * The published main photo of the first offering of one of the group's categories, with where it is.
     *
     * @param  array{categories: list<array<string, mixed>>}  $group
     * @param  Collection<int, Offering>  $offerings
     * @param  array<int, array<string, mixed>>  $photos
     * @return array<string, mixed>|null
     */
    private function groupPhoto(array $group, Collection $offerings, array $photos): ?array
    {
        $keys = array_column($group['categories'], 'key');
        $offering = $offerings->first(fn (Offering $offering): bool => in_array($offering->product->key, $keys, true) && isset($photos[$offering->getKey()]));

        return $offering === null ? null : [...$photos[$offering->getKey()], 'caption' => $offering->product->name.' at SmartGro '.$offering->property->name];
    }
}
