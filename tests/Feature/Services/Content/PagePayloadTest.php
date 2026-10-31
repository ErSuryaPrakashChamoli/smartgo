<?php

use App\Models\Page;
use App\Models\Product;
use App\Services\Content\PagePayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

uses(RefreshDatabase::class);

/**
 * @param  list<array{type: string, data: array<string, mixed>}>  $blocks
 * @return array<string, mixed>
 */
function payloadWithBlocks(array $blocks, string $slug = 'about'): array
{
    return ['title' => 'About SmartGro', 'slug' => $slug, 'template' => 'standard', 'blocks' => $blocks];
}

/**
 * @return array<string, list<string>> validation errors, or an empty array when the payload passes
 */
function payloadErrors(array $payload, bool $forSubmission = true, ?Page $page = null): array
{
    try {
        app(PagePayload::class)->validate($page ?? Page::factory()->create(), $payload, $forSubmission);
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('accepts a complete page built from every block in the library', function () {
    $linkedPage = Page::factory()->create();

    expect(payloadErrors(payloadWithBlocks([
        ['type' => 'hero', 'data' => ['eyebrow' => 'Noida', 'heading' => 'Workspaces in Noida', 'intro' => 'Desks and cabins.', 'link' => ['label' => 'Contact us', 'type' => 'page', 'page_id' => $linkedPage->id, 'url' => null]]],
        ['type' => 'text', 'data' => ['heading' => 'About us', 'body' => '<p>We run <strong>four</strong> properties.</p>']],
        ['type' => 'features', 'data' => ['heading' => 'Why SmartGro', 'intro' => null, 'items' => [['title' => 'Quiet', 'text' => 'Calm floors.'], ['title' => 'Close', 'text' => 'Near the metro.']]]],
        ['type' => 'faq', 'data' => ['heading' => null, 'items' => [['question' => 'Can I visit?', 'answer' => 'Yes, book a tour.']]]],
        ['type' => 'cta', 'data' => ['eyebrow' => null, 'heading' => 'Visit us', 'text' => null, 'primary_link' => ['label' => 'Our site', 'type' => 'url', 'page_id' => null, 'url' => 'https://example.com'], 'secondary_link' => null]],
    ])))->toBe([]);
});

it('lets a draft be saved incomplete but requires a complete version for submission', function () {
    $incomplete = ['title' => null, 'slug' => null, 'blocks' => [['type' => 'faq', 'data' => ['heading' => null, 'items' => [['question' => 'Can I visit?', 'answer' => null]]]]]];

    expect(payloadErrors($incomplete, forSubmission: false))->toBe([])
        ->and(array_keys(payloadErrors($incomplete)))->toContain('title', 'slug', 'blocks.0.data.items.0.answer');
});

it('requires at least one section before submission', function () {
    expect(payloadErrors(payloadWithBlocks([]))['blocks'])->toBe(['Add at least one section.']);
});

it('refuses unknown block types and unexpected fields', function () {
    expect(payloadErrors(payloadWithBlocks([['type' => 'html', 'data' => ['code' => '<script>alert(1)</script>']]]), forSubmission: false))
        ->toHaveKey('blocks.0.type')
        ->and(payloadErrors(payloadWithBlocks([['type' => 'faq', 'data' => ['heading' => null, 'items' => [], 'onclick' => 'x']]]), forSubmission: false))
        ->toHaveKey('blocks.0.data');
});

it('enforces field lengths', function () {
    $errors = payloadErrors(payloadWithBlocks([['type' => 'hero', 'data' => ['eyebrow' => null, 'heading' => str_repeat('a', 91), 'intro' => null, 'link' => null]]]), forSubmission: false);

    expect($errors['blocks.0.data.heading'][0])->toContain('section 1 (Introduction) heading')->toContain('90');
});

it('refuses rich text with executable content even in a draft', function () {
    $errors = payloadErrors(payloadWithBlocks([['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Hi</p><img src=x onerror="alert(1)">']]]), forSubmission: false);

    expect($errors)->toHaveKey('blocks.0.data.body');
});

it('stores rich text sanitised', function () {
    $payload = app(PagePayload::class)->validate(Page::factory()->create(), payloadWithBlocks([
        ['type' => 'text', 'data' => ['heading' => null, 'body' => '<p style="color:red">Hello <span>there</span></p>']],
    ]), forSubmission: false);

    expect($payload['blocks'][0]['data']['body'])->toBe('<p>Hello there</p>');
});

it('refuses link targets that are not https or not a page', function (array $link, string $field) {
    $errors = payloadErrors(payloadWithBlocks([['type' => 'cta', 'data' => ['eyebrow' => null, 'heading' => 'Visit', 'text' => null, 'primary_link' => $link, 'secondary_link' => null]]]));

    expect($errors)->toHaveKey("blocks.0.data.primary_link.{$field}");
})->with([
    'javascript' => [['label' => 'Go', 'type' => 'url', 'page_id' => null, 'url' => 'javascript:alert(1)'], 'url'],
    'plain http' => [['label' => 'Go', 'type' => 'url', 'page_id' => null, 'url' => 'http://example.com'], 'url'],
    'protocol relative' => [['label' => 'Go', 'type' => 'url', 'page_id' => null, 'url' => '//evil.example'], 'url'],
    'typed path' => [['label' => 'Go', 'type' => 'url', 'page_id' => null, 'url' => '/about'], 'url'],
    'missing page' => [['label' => 'Go', 'type' => 'page', 'page_id' => 999999, 'url' => null], 'page_id'],
    'missing workspace type' => [['label' => 'Go', 'type' => 'category', 'product_id' => 999999], 'product_id'],
    'no workspace type chosen' => [['label' => 'Go', 'type' => 'category', 'product_id' => null], 'product_id'],
    'unknown kind' => [['label' => 'Go', 'type' => 'script', 'page_id' => null, 'url' => null], 'type'],
]);

it('allows the introduction only once, as the first section', function () {
    $introduction = ['type' => 'hero', 'data' => ['eyebrow' => null, 'heading' => 'Hello', 'intro' => null, 'link' => null]];
    $text = ['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Text</p>']];

    expect(payloadErrors(payloadWithBlocks([$text, $introduction]))['blocks'])->toBe(['The introduction can be used once, as the first section.'])
        ->and(payloadErrors(payloadWithBlocks([$introduction, $introduction])))->toHaveKey('blocks')
        ->and(payloadErrors(payloadWithBlocks([$introduction, $text])))->toBe([]);
});

it('refuses page addresses that are malformed or used by another part of the website', function (string $slug) {
    expect(payloadErrors(payloadWithBlocks([['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Text</p>']]], $slug)))->toHaveKey('slug');
})->with(['Upper-Case', 'trailing/', '../etc', 'with space', 'admin', 'admin/settings', 'design', 'workspaces/new', 'find-a-workspace', 'locations', 'up']);

it('accepts nested page addresses', function () {
    expect(payloadErrors(payloadWithBlocks([['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Text</p>']]], 'legal/privacy')))->toBe([]);
});

it('refuses an address another live page uses, but not the page\'s own', function () {
    $page = Page::factory()->create(['status' => 'published', 'slug' => 'about', 'title' => 'About']);
    $payload = payloadWithBlocks([['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Text</p>']]]);

    expect(payloadErrors($payload)['slug'])->toBe(['Another page already uses this address.'])
        ->and(payloadErrors($payload, page: $page))->toBe([]);
});

it('accepts cards whose links go to a page, a part of the website or another website, and refuses incomplete cards (OD-143)', function () {
    $linkedPage = Page::factory()->create(['title' => 'Startups']);
    $cards = fn (array $items): array => payloadWithBlocks([['type' => 'cards', 'data' => ['heading' => 'Solutions', 'intro' => null, 'items' => $items]]]);

    expect(payloadErrors($cards([
        ['title' => 'Startups', 'text' => 'Room to grow.', 'link' => ['label' => 'Read more', 'type' => 'page', 'page_id' => $linkedPage->id, 'section' => null, 'url' => null]],
        ['title' => 'Book a visit', 'text' => null, 'link' => ['label' => 'Book a tour', 'type' => 'section', 'page_id' => null, 'section' => 'book_a_tour', 'url' => null]],
        ['title' => 'Partner', 'text' => null, 'link' => ['label' => 'Visit', 'type' => 'url', 'page_id' => null, 'section' => null, 'url' => 'https://example.com']],
    ])))->toBe([]);

    expect(payloadErrors($cards([['title' => 'Only one', 'text' => null, 'link' => null]])))->toHaveKey('blocks.0.data.items')
        ->and(payloadErrors($cards([['title' => '', 'text' => null, 'link' => null], ['title' => 'Second', 'text' => null, 'link' => null]])))->toHaveKey('blocks.0.data.items.0.title')
        ->and(payloadErrors($cards([['title' => 'One', 'text' => null, 'link' => ['label' => 'Go', 'type' => 'section', 'page_id' => null, 'section' => 'admin', 'url' => null]], ['title' => 'Two', 'text' => null, 'link' => null]])))->toHaveKey('blocks.0.data.items.0.link.section')
        ->and(payloadErrors($cards([['title' => 'One', 'text' => null, 'link' => ['label' => 'Go', 'type' => 'section', 'page_id' => null, 'section' => null, 'url' => null]], ['title' => 'Two', 'text' => null, 'link' => null]])))->toHaveKey('blocks.0.data.items.0.link.section');
});

it('accepts a link to a workspace type, keeping the chosen type (OD-150)', function () {
    $product = Product::factory()->create();

    $payload = app(PagePayload::class)->validate(Page::factory()->create(), payloadWithBlocks([['type' => 'hero', 'data' => ['eyebrow' => null, 'heading' => 'Desks', 'intro' => null,
        'link' => ['label' => 'See locations', 'type' => 'category', 'page_id' => null, 'section' => null, 'product_id' => $product->id, 'url' => null]]]]), forSubmission: true);

    expect($payload['blocks'][0]['data']['link'])->toMatchArray(['type' => 'category', 'product_id' => $product->id]);
});

it('accepts the sample layout for example content (OD-143)', function () {
    expect(payloadErrors([...payloadWithBlocks([['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Example.</p>']]]), 'template' => 'sample']))->toBe([])
        ->and(payloadErrors([...payloadWithBlocks([['type' => 'text', 'data' => ['heading' => null, 'body' => '<p>Example.</p>']]]), 'template' => 'draft-only']))->toHaveKey('template');
});
