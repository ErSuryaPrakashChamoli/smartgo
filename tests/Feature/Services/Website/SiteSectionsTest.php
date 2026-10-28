<?php

use App\Models\CaseStudy;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Models\Product;
use App\Models\Property;
use App\Services\Website\SiteSections;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('offers the home page and its contact section at all times, and lists only once they have something to show', function () {
    $keys = array_keys(SiteSections::SECTIONS);

    expect(app(SiteSections::class)->availablePaths($keys))->toBe(['home' => '/', 'contact' => '/#contact']);

    Product::factory()->create(['is_active' => true]);
    Property::factory()->listed()->create();
    Page::factory()->published()->create(['slug' => 'legal/privacy', 'template' => 'legal']);
    Post::factory()->published()->create();
    CaseStudy::factory()->published()->create();
    Faq::factory()->published()->create();

    expect(app(SiteSections::class)->availablePaths($keys))->toBe(array_map(fn (array $section): string => $section['path'], SiteSections::SECTIONS));
});

it('keeps drafts, closed or unpublished properties, inactive categories, stories without consent and a closed enquiry form out of the menus', function () {
    Product::factory()->create(['is_active' => false]);
    Property::factory()->create();
    Property::factory()->open()->create();
    Page::factory()->create(['slug' => 'legal/privacy', 'template' => 'legal']);
    Post::factory()->create();
    CaseStudy::factory()->published()->create(['consent_on_file' => false]);
    Faq::factory()->create();

    expect(app(SiteSections::class)->availablePaths(['workspaces', 'workspace_types', 'locations', 'enquire', 'insights', 'customers', 'faqs']))->toBe([]);
});

it('ignores keys that are not a part of the website', function () {
    expect(app(SiteSections::class)->availablePaths(['admin', 'home']))->toBe(['home' => '/']);
});
