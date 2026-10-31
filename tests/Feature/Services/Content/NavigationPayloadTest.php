<?php

use App\Models\NavigationMenu;
use App\Models\Page;
use App\Models\Product;
use App\Models\Property;
use App\Services\Content\NavigationPayload;
use Database\Seeders\ReferenceDataSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\seed;

uses(RefreshDatabase::class);

function menuNamed(string $handle): NavigationMenu
{
    return NavigationMenu::query()->where('handle', $handle)->sole();
}

/**
 * @return array<string, list<string>>
 */
function menuErrors(string $handle, array $items, bool $forSubmission = true): array
{
    try {
        app(NavigationPayload::class)->validate(menuNamed($handle), ['items' => $items], $forSubmission);
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

/**
 * @return array{label: string, type: string, page_id: int|null, url: string|null}
 */
function pageLink(string $label, Page $page): array
{
    return ['label' => $label, 'type' => 'page', 'page_id' => $page->id, 'url' => null];
}

it('creates the four fixed menus empty', function () {
    expect(NavigationMenu::query()->orderBy('id')->pluck('name', 'handle')->all())->toBe([
        'main' => 'Main menu', 'actions' => 'Header buttons', 'footer' => 'Footer', 'legal' => 'Legal links',
    ])->and(NavigationMenu::query()->whereNotNull('published_revision_id')->count())->toBe(0);
});

it('accepts links to pages and other websites, and groups where the menu allows them', function () {
    $about = Page::factory()->published()->create();

    expect(menuErrors('main', [
        pageLink('About', $about),
        ['label' => 'Partners', 'type' => 'url', 'page_id' => null, 'url' => 'https://partner.example/smartgro'],
        ['label' => 'Company', 'type' => 'group', 'page_id' => null, 'url' => null, 'children' => [pageLink('About', $about)]],
    ]))->toBe([])
        ->and(menuErrors('footer', [['label' => 'Company', 'type' => 'group', 'page_id' => null, 'url' => null, 'children' => [pageLink('About', $about)]]]))->toBe([])
        ->and(menuErrors('legal', [pageLink('Privacy', $about)]))->toBe([]);
});

it('accepts links to parts of the website and to locations, keeping only the chosen destination', function () {
    $property = Property::factory()->create();

    $payload = app(NavigationPayload::class)->validate(menuNamed('main'), ['items' => [
        ['label' => 'Locations', 'type' => 'section', 'section' => 'locations', 'property_id' => $property->id],
        ['label' => 'Centres', 'type' => 'group', 'children' => [['label' => 'A-70', 'type' => 'property', 'property_id' => $property->id, 'section' => 'faqs']]],
    ]], forSubmission: true);

    expect($payload['items'][0])->toMatchArray(['type' => 'section', 'section' => 'locations', 'property_id' => null, 'page_id' => null, 'url' => null])
        ->and($payload['items'][1]['children'][0])->toMatchArray(['type' => 'property', 'property_id' => $property->id, 'section' => null]);
});

it('accepts links to workspace types, keeping only the chosen destination, and requires the type (OD-143)', function () {
    seed(ReferenceDataSeeder::class);
    $product = Product::query()->firstOrFail();

    $payload = app(NavigationPayload::class)->validate(menuNamed('main'), ['items' => [
        ['label' => 'Spaces', 'type' => 'group', 'children' => [['label' => $product->name, 'type' => 'category', 'product_id' => $product->id, 'section' => 'faqs']]],
    ]], forSubmission: true);

    expect($payload['items'][0]['children'][0])->toMatchArray(['type' => 'category', 'product_id' => $product->id, 'section' => null, 'property_id' => null])
        ->and(menuErrors('main', [['label' => 'Desks', 'type' => 'category', 'product_id' => null]]))->toHaveKey('items.0.product_id')
        ->and(menuErrors('main', [['label' => 'Desks', 'type' => 'category', 'product_id' => 999999]]))->toHaveKey('items.0.product_id');
});

it('accepts sub-groups inside a main-menu group, one level deep and never empty, and nowhere else (OD-145)', function () {
    $about = Page::factory()->published()->create();
    $subGroup = ['label' => 'Coworking', 'type' => 'group', 'children' => [pageLink('About', $about)]];

    $payload = app(NavigationPayload::class)->validate(menuNamed('main'), ['items' => [
        ['label' => 'Spaces', 'type' => 'group', 'children' => [pageLink('All', $about), $subGroup]],
    ]], forSubmission: true);

    expect($payload['items'][0]['children'][1])->toMatchArray(['label' => 'Coworking', 'type' => 'group'])
        ->and($payload['items'][0]['children'][1]['children'][0])->toMatchArray(['type' => 'page', 'page_id' => $about->id])
        ->and($payload['items'][0]['children'][0])->not->toHaveKey('children')
        ->and(menuErrors('main', [['label' => 'Spaces', 'type' => 'group', 'children' => [['label' => 'Empty', 'type' => 'group', 'children' => []]]]]))->toHaveKey('items.0.children')
        ->and(menuErrors('main', [['label' => 'Spaces', 'type' => 'group', 'children' => [[...pageLink('About', $about), 'children' => [pageLink('About', $about)]]]]]))->toHaveKey('items.0.children')
        ->and(menuErrors('main', [['label' => 'Spaces', 'type' => 'group', 'children' => [['label' => 'Deep', 'type' => 'group', 'children' => [$subGroup]]]]]))->toHaveKey('items.0.children.0.children.0.type')
        ->and(menuErrors('footer', [['label' => 'Company', 'type' => 'group', 'children' => [$subGroup]]]))->toHaveKey('items.0.children.0.type');
});

it('keeps a description and an icon on drop-down links and categories only, and refuses an unknown icon or markup (OD-149)', function () {
    $about = Page::factory()->published()->create();
    $detailed = fn (array $item, ?string $description, ?string $icon): array => [...$item, 'description' => $description, 'icon' => $icon];
    $inSolutions = fn (array $child): array => [['label' => 'Solutions', 'type' => 'group', 'children' => [$child]]];

    $payload = app(NavigationPayload::class)->validate(menuNamed('main'), ['items' => [$detailed(['label' => 'Solutions', 'type' => 'group', 'children' => [
        $detailed(['label' => 'Workspaces', 'type' => 'group', 'children' => [$detailed(pageLink('Desks', $about), '  A desk of your own.  ', 'computer-desktop')]], 'Desks and offices.', 'building-office'),
        $detailed(pageLink('About', $about), null, null),
    ]], 'Top level', 'briefcase')]], forSubmission: true);
    $footer = app(NavigationPayload::class)->validate(menuNamed('footer'), ['items' => [
        ['label' => 'Company', 'type' => 'group', 'children' => [$detailed(pageLink('About', $about), 'Who we are.', 'sparkles')]],
    ]], forSubmission: true);

    expect($payload['items'][0])->not->toHaveKey('description')->not->toHaveKey('icon')
        ->and($payload['items'][0]['children'][0])->toMatchArray(['label' => 'Workspaces', 'description' => 'Desks and offices.', 'icon' => 'building-office'])
        ->and($payload['items'][0]['children'][0]['children'][0])->toMatchArray(['label' => 'Desks', 'description' => 'A desk of your own.', 'icon' => 'computer-desktop'])
        ->and($payload['items'][0]['children'][1])->toMatchArray(['description' => null, 'icon' => null])
        ->and($footer['items'][0]['children'][0])->not->toHaveKey('description')->not->toHaveKey('icon')
        ->and(menuErrors('main', $inSolutions($detailed(pageLink('About', $about), null, 'bomb')))['items.0.children.0.icon'])->toBe(['Choose an icon from the list.'])
        ->and(menuErrors('main', $inSolutions($detailed(pageLink('About', $about), '<b>Hi</b>', null)))['items.0.children.0.description'])->toBe(['Use plain text: < and > are not allowed.'])
        ->and(menuErrors('main', $inSolutions(['label' => 'W', 'type' => 'group', 'children' => [$detailed(pageLink('About', $about), str_repeat('a', 121), null)]])))
        ->toHaveKey('items.0.children.0.children.0.description');
});

it('stores items as an ordered list and drops links under items that are not groups', function () {
    $about = Page::factory()->published()->create();

    $payload = app(NavigationPayload::class)->validate(menuNamed('main'), ['items' => [
        'b' => pageLink('Second', $about),
        'a' => ['label' => 'First', 'type' => 'group', 'page_id' => null, 'url' => null, 'children' => ['x' => pageLink('Child', $about)]],
    ]], forSubmission: true);

    expect(array_column($payload['items'], 'label'))->toBe(['Second', 'First'])
        ->and($payload['items'][0]['children'])->toBe([])
        ->and($payload['items'][1]['children'][0]['label'])->toBe('Child');
});

it('refuses destinations that are unsafe or point back at this website', function (array $item, string $field) {
    expect(menuErrors('legal', [$item]))->toHaveKey("items.0.{$field}");
})->with([
    'javascript' => [['label' => 'X', 'type' => 'url', 'page_id' => null, 'url' => 'javascript:alert(1)'], 'url'],
    'plain http' => [['label' => 'X', 'type' => 'url', 'page_id' => null, 'url' => 'http://partner.example'], 'url'],
    'protocol relative' => [['label' => 'X', 'type' => 'url', 'page_id' => null, 'url' => '//partner.example'], 'url'],
    'typed path' => [['label' => 'X', 'type' => 'url', 'page_id' => null, 'url' => '/about'], 'url'],
    'missing page' => [['label' => 'X', 'type' => 'page', 'page_id' => 999999, 'url' => null], 'page_id'],
    'unknown part of the website' => [['label' => 'X', 'type' => 'section', 'section' => 'admin'], 'section'],
    'no part of the website chosen' => [['label' => 'X', 'type' => 'section', 'section' => null], 'section'],
    'missing location' => [['label' => 'X', 'type' => 'property', 'property_id' => 999999], 'property_id'],
    'no page chosen' => [['label' => 'X', 'type' => 'page', 'page_id' => null, 'url' => null], 'page_id'],
    'markup in the label' => [['label' => '<b>X</b>', 'type' => 'url', 'page_id' => null, 'url' => 'https://partner.example'], 'label'],
    'unknown kind' => [['label' => 'X', 'type' => 'script', 'page_id' => null, 'url' => null], 'type'],
]);

it('refuses typed addresses on this website, so no back-office or preview link is published', function () {
    $ownSite = preg_replace('#^http://#', 'https://', url('/admin/content/revisions/1/preview?signature=abc'));

    expect(menuErrors('legal', [['label' => 'X', 'type' => 'url', 'page_id' => null, 'url' => $ownSite]])['items.0.url'])
        ->toBe(['Pick the page from the list instead of typing an address on this website.']);
});

it('enforces each menu\'s shape', function () {
    $page = Page::factory()->published()->create();
    $group = ['label' => 'Company', 'type' => 'group', 'page_id' => null, 'url' => null, 'children' => [pageLink('About', $page)]];

    expect(menuErrors('legal', [$group]))->toHaveKey('items.0.type')
        ->and(menuErrors('actions', [$group]))->toHaveKey('items.0.type')
        ->and(menuErrors('footer', [pageLink('About', $page)]))->toHaveKey('items.0.type')
        ->and(menuErrors('actions', [pageLink('A', $page), pageLink('B', $page), pageLink('C', $page)]))->toHaveKey('items')
        ->and(menuErrors('main', [[...pageLink('About', $page), 'children' => [pageLink('Child', $page)]]])['items'][0])->toContain('cannot hold other links')
        ->and(menuErrors('main', [['label' => 'Empty', 'type' => 'group', 'page_id' => null, 'url' => null, 'children' => []]])['items'][0])->toContain('needs at least one link')
        ->and(menuErrors('main', [['label' => 'Empty', 'type' => 'group', 'page_id' => null, 'url' => null, 'children' => []]], forSubmission: false))->toBe([]);
});
