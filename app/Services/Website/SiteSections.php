<?php

namespace App\Services\Website;

use App\Models\CaseStudy;
use App\Models\Faq;
use App\Models\Post;
use App\Models\Product;
use App\Models\Property;

/**
 * The fixed parts of the website a menu can link to without a CMS page: the home page and its sections,
 * the workspace types, the locations list, the enquiry form, and the insights, customer stories and FAQ
 * lists (FR-CMS-4). A part appears only while it has something published to show, so a menu never leads
 * to an empty list or a closed form.
 */
class SiteSections
{
    /**
     * @var array<string, array{label: string, path: string}>
     */
    public const array SECTIONS = [
        'home' => ['label' => 'Home page', 'path' => '/'],
        'workspaces' => ['label' => 'Workspace types (on the home page)', 'path' => '/#workspaces'],
        'workspace_types' => ['label' => 'All workspace types (page)', 'path' => '/workspaces'],
        'locations' => ['label' => 'All locations', 'path' => '/locations'],
        'contact' => ['label' => 'Contact (on the home page)', 'path' => '/#contact'],
        'enquire' => ['label' => 'Enquiry form', 'path' => '/enquire'],
        'insights' => ['label' => 'Insights', 'path' => '/insights'],
        'customers' => ['label' => 'Customer stories', 'path' => '/customers'],
        'faqs' => ['label' => 'FAQs', 'path' => '/faqs'],
    ];

    /**
     * @return array<string, string> key => label, for the menu editor
     */
    public function options(): array
    {
        return array_map(fn (array $section): string => $section['label'], self::SECTIONS);
    }

    /**
     * Addresses of the given sections that have something to show, keyed by section.
     *
     * @param  list<string>  $keys
     * @return array<string, string>
     */
    public function availablePaths(array $keys): array
    {
        $paths = [];

        foreach (array_unique($keys) as $key) {
            if (isset(self::SECTIONS[$key]) && $this->hasContent($key)) {
                $paths[$key] = self::SECTIONS[$key]['path'];
            }
        }

        return $paths;
    }

    private function hasContent(string $key): bool
    {
        return match ($key) {
            'workspaces', 'workspace_types' => Product::query()->shown()->exists(),
            'enquire' => app(EnquiryForm::class)->isOpen(),
            'locations' => Property::query()->publiclyListed()->exists(),
            'insights' => Post::query()->published()->exists(),
            'customers' => CaseStudy::query()->published()->where('consent_on_file', true)->exists(),
            'faqs' => Faq::query()->published()->exists(),
            default => true,
        };
    }
}
