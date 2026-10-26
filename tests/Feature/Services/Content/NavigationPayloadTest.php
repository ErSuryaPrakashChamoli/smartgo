<?php

use App\Models\NavigationMenu;
use App\Models\Page;
use App\Services\Content\NavigationPayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

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
