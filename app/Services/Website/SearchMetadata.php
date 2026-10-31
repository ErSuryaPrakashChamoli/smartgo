<?php

namespace App\Services\Website;

use App\Filament\Clusters\Properties\Resources\OfferingProfiles\OfferingProfileResource;
use App\Filament\Clusters\Properties\Resources\PropertyProfiles\PropertyProfileResource;
use App\Filament\Clusters\Website\Resources\CaseStudies\CaseStudyResource;
use App\Filament\Clusters\Website\Resources\Pages\PageResource;
use App\Filament\Clusters\Website\Resources\Posts\PostResource;
use App\Models\City;
use App\Models\Offering;
use App\Models\Page;
use App\Models\Product;
use App\Models\Property;
use App\Services\Content\PageRenderer;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * The search titles and descriptions of every page the website lets search engines index (FR-SEO-1's
 * "titles and descriptions unique site-wide (validator report)"): the same pages as the sitemap, with the
 * title and description each page writes in its head, worked out from the same records and the same
 * wording as its template (a test compares them with the rendered pages). It flags titles or descriptions
 * used by more than one page and pages without a description. The further pages of one list share their
 * list's title and description by design, so that repetition is reported as expected, not as a problem.
 * It changes nothing: staff fix a finding in the item's own editor, through its approval workflow.
 */
class SearchMetadata
{
    public const string DEFAULT_TITLE = 'SmartGro · Work smart. Grow together.';

    public const string DUPLICATE_TITLE = 'duplicate_title';

    public const string DUPLICATE_DESCRIPTION = 'duplicate_description';

    public const string MISSING_DESCRIPTION = 'missing_description';

    public const string EXPECTED_REPEAT = 'expected_repeat';

    /**
     * @var array<string, string>
     */
    public const array FINDINGS = [
        self::DUPLICATE_TITLE => 'Title used by another page',
        self::DUPLICATE_DESCRIPTION => 'Description used by another page',
        self::MISSING_DESCRIPTION => 'No description',
        self::EXPECTED_REPEAT => 'Further page of a list (expected)',
    ];

    /**
     * @var array<string, string>
     */
    public const array TYPES = [
        'home' => 'Home page', 'workspaces' => 'Workspaces hub', 'category' => 'Workspace type', 'category_city' => 'Workspace type in a city',
        'locations' => 'Locations hub', 'city' => 'City', 'sector' => 'Sector', 'property' => 'Property', 'offering' => 'Workspace at a property',
        'insights' => 'Article list', 'topic' => 'Article topic', 'post' => 'Article', 'customers' => 'Customer story list', 'case_study' => 'Customer story',
        'faqs' => 'Questions page', 'enquire' => 'Enquiry page', 'book_a_tour' => 'Tour page', 'page' => 'Page',
    ];

    public function __construct(private Sitemap $sitemap, private WorkspaceCatalogue $catalogue, private PageRenderer $pageRenderer) {}

    /**
     * One row per page, with its findings.
     *
     * @return Collection<int, array{key: string, type: string, page: string, url: string, title: string, description: string|null, source: string, edit_url: string|null, findings: list<string>}>
     */
    public function rows(): Collection
    {
        $entries = $this->sitemap->entries();
        $cityNames = $entries->where('type', 'property')->map(fn (array $entry): string => $entry['subject']->city->name)->unique()->values()->all();

        $rows = $entries->map(fn (array $entry): array => [
            'key' => $entry['path'],
            'type' => self::TYPES[$entry['type']] ?? $entry['type'],
            'url' => $entry['loc'],
            'list' => $entry['context']['list'] ?? null,
            ...$this->describe($entry, $cityNames),
        ]);

        $titleGroups = $rows->groupBy(fn (array $row): string => $this->normalised($row['title']));
        $descriptionGroups = $rows->filter(fn (array $row): bool => filled($row['description']))->groupBy(fn (array $row): string => $this->normalised((string) $row['description']));

        return $rows->map(function (array $row) use ($titleGroups, $descriptionGroups): array {
            $findings = [];

            foreach ([self::DUPLICATE_TITLE => $titleGroups->get($this->normalised($row['title'])), self::DUPLICATE_DESCRIPTION => filled($row['description']) ? $descriptionGroups->get($this->normalised((string) $row['description'])) : null] as $finding => $group) {
                if ($group === null || $group->count() < 2) {
                    continue;
                }

                $findings[] = $row['list'] !== null && $group->every(fn (array $other): bool => $other['list'] === $row['list']) ? self::EXPECTED_REPEAT : $finding;
            }

            if (blank($row['description'])) {
                $findings[] = self::MISSING_DESCRIPTION;
            }

            unset($row['list']);

            return [...$row, 'findings' => array_values(array_unique($findings))];
        })->values();
    }

    /**
     * The page's title and description as its template writes them, where they come from and where staff
     * change them.
     *
     * @param  array{type: string, subject: mixed, context: array<string, mixed>}  $entry
     * @param  list<string>  $cityNames
     * @return array{page: string, title: string, description: string|null, source: string, edit_url: string|null}
     */
    private function describe(array $entry, array $cityNames): array
    {
        $subject = $entry['subject'];
        $template = fn (string $page, ?string $title, ?string $description): array => ['page' => $page, 'title' => $this->title(null, $title), 'description' => $description, 'source' => 'Website template', 'edit_url' => null];

        return match ($entry['type']) {
            'home' => $template('Home page', null, 'SmartGro flexible workspaces: private offices, desks, meeting rooms, virtual offices and managed offices'.($cityNames === [] ? '' : ' in '.implode(', ', $cityNames)).'.'),
            'workspaces' => $template('Workspaces', 'Workspaces', 'Private offices and cabins, dedicated and hot desks, meeting and conference rooms, virtual offices and managed offices at SmartGro in Noida.'),
            'category', 'category_city' => $this->category($subject, $entry['context']),
            'locations' => $template('Locations', 'Locations', 'SmartGro workspace locations.'),
            'city' => $template($subject->name, 'Workspaces in '.$subject->name, 'SmartGro workspace locations in '.$subject->name.'.'),
            'sector' => $template($subject->name.', '.$subject->city->name, 'Workspaces in '.$subject->name.', '.$subject->city->name, 'SmartGro workspace locations in '.$subject->name.', '.$subject->city->name.'.'),
            'property' => $this->property($subject),
            'offering' => $this->offering($subject),
            'insights' => $template('Insights'.$this->pageSuffix($entry), 'Insights', 'Articles and guides from SmartGro.'),
            'topic' => $template($subject->name.$this->pageSuffix($entry), $subject->name.' — Insights', 'Articles about '.$subject->name.' from SmartGro.'),
            'post' => ['page' => (string) $subject->title, 'title' => $this->title(null, $subject->title), 'description' => filled($subject->excerpt) ? $subject->excerpt : null, 'source' => 'Article title and summary', 'edit_url' => PostResource::getUrl('edit', ['record' => $subject])],
            'customers' => $template('Customer stories', 'Customer stories', 'How companies work at SmartGro.'),
            'case_study' => ['page' => (string) $subject->title, 'title' => $this->title(null, $subject->title), 'description' => filled($subject->summary) ? $subject->summary : null, 'source' => 'Customer story title and summary', 'edit_url' => CaseStudyResource::getUrl('edit', ['record' => $subject])],
            'faqs' => $template('Questions', 'Frequently asked questions', 'Answers to common questions about SmartGro workspaces.'),
            'enquire' => $template('Enquiry page', 'Enquire', 'Ask SmartGro about workspaces in Noida. An enquiry does not reserve anything.'),
            'book_a_tour' => $template('Tour page', 'Book a tour', 'Visit a SmartGro location. A tour request is confirmed by SmartGro; nothing is reserved or charged.'),
            default => $this->page($subject),
        };
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array{page: string, title: string, description: string|null, source: string, edit_url: string|null}
     */
    private function category(Product $product, array $context): array
    {
        /** @var City|null $city */
        $city = $context['city'] ?? null;
        $count = (int) ($context['offerings'] ?? 0);
        $name = $product->name.($city === null ? '' : ' in '.$city->name);

        return [
            'page' => $name,
            'title' => $this->title(null, $name),
            'description' => ($this->catalogue->categoryCopy($product)['summary'] ?? $product->name).' Available at '.$count.' SmartGro '.($count === 1 ? 'location' : 'locations').($city === null ? '' : ' in '.$city->name).'.',
            'source' => 'Website template',
            'edit_url' => null,
        ];
    }

    /**
     * @return array{page: string, title: string, description: string|null, source: string, edit_url: string|null}
     */
    private function property(Property $property): array
    {
        $profile = $this->catalogue->publishedProfile($property) ?? [];

        return [
            'page' => 'SmartGro '.$property->name,
            'title' => $this->title($profile['seoTitle'] ?? null, 'SmartGro '.$property->name.', '.$property->city->name),
            'description' => $profile['seoDescription'] ?? $profile['summary'] ?? 'SmartGro '.$property->name.': '.$property->addressLine().'.',
            'source' => filled($profile['seoTitle'] ?? null) ? 'Search title written for the property' : 'Property name and summary',
            'edit_url' => $property->profile === null ? null : PropertyProfileResource::getUrl('edit', ['record' => $property->profile]),
        ];
    }

    /**
     * @return array{page: string, title: string, description: string|null, source: string, edit_url: string|null}
     */
    private function offering(Offering $offering): array
    {
        $profile = $this->catalogue->offeringProfile($offering->profile->toRevisionPayload(), $offering);
        $name = $offering->product->name.' at SmartGro '.$offering->property->name;

        return [
            'page' => $name,
            'title' => $this->title($profile['seoTitle'] ?? null, $name.', '.$offering->property->city->name),
            'description' => $profile['seoDescription'] ?? $profile['headline'] ?? $name.'.',
            'source' => filled($profile['seoTitle'] ?? null) ? 'Search title written for the workspace' : 'Workspace name and headline',
            'edit_url' => OfferingProfileResource::getUrl('edit', ['record' => $offering->profile]),
        ];
    }

    /**
     * @return array{page: string, title: string, description: string|null, source: string, edit_url: string|null}
     */
    private function page(Page $page): array
    {
        $content = $this->pageRenderer->searchFields($page->toRevisionPayload());

        return [
            'page' => $content['title'] !== '' ? $content['title'] : '/'.$page->slug,
            'title' => $this->title($content['metaTitle'], $content['title']),
            'description' => $content['metaDescription'],
            'source' => filled($content['metaTitle']) ? 'Search title written for the page' : 'Page title and introduction',
            'edit_url' => PageResource::getUrl('edit', ['record' => $page]),
        ];
    }

    /**
     * The title as the website layout writes it.
     */
    private function title(?string $metaTitle, ?string $title): string
    {
        return filled($metaTitle) ? $metaTitle : (filled($title) ? $title.' · SmartGro' : self::DEFAULT_TITLE);
    }

    /**
     * @param  array{context: array<string, mixed>}  $entry
     */
    private function pageSuffix(array $entry): string
    {
        return ($entry['context']['page'] ?? 1) > 1 ? ', page '.$entry['context']['page'] : '';
    }

    private function normalised(string $text): string
    {
        return Str::lower(trim((string) preg_replace('/\s+/u', ' ', $text)));
    }
}
