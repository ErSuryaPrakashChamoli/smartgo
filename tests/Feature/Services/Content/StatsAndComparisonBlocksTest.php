<?php

use App\Models\Page;
use App\Services\Content\PagePayload;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;

use function Pest\Laravel\get;

uses(RefreshDatabase::class);

/**
 * @return array{type: string, data: array<string, mixed>}
 */
function statsBlock(array $overrides = []): array
{
    return ['type' => 'stats', 'data' => ['heading' => 'In numbers', 'items' => [['value' => '4', 'label' => 'centres in Noida'], ['value' => '24/7', 'label' => 'access']], 'source' => 'Owner property list, October 2026', ...$overrides]];
}

/**
 * @return array{type: string, data: array<string, mixed>}
 */
function comparisonBlock(array $overrides = []): array
{
    return ['type' => 'comparison', 'data' => [
        'heading' => 'Desk or cabin?', 'intro' => null,
        'column_1' => 'Hot desk', 'column_2' => 'Private cabin', 'column_3' => null, 'column_4' => null,
        'rows' => [['label' => 'Best for', 'cell_1' => 'Individuals', 'cell_2' => 'Teams', 'cell_3' => null, 'cell_4' => null]],
        ...$overrides,
    ]];
}

/**
 * @param  array{type: string, data: array<string, mixed>}  $block
 * @return array<string, list<string>>
 */
function blockErrors(array $block): array
{
    try {
        app(PagePayload::class)->validate(Page::factory()->create(), ['title' => 'About', 'slug' => 'about', 'template' => 'standard', 'blocks' => [$block]], true);
    } catch (ValidationException $exception) {
        return $exception->errors();
    }

    return [];
}

it('accepts complete figures and comparison tables', function () {
    expect(blockErrors(statsBlock()))->toBe([])
        ->and(blockErrors(comparisonBlock()))->toBe([]);
});

it('needs two to four figures and where they come from before submission', function (array $overrides, string $field) {
    expect(blockErrors(statsBlock($overrides)))->toHaveKey("blocks.0.data.{$field}");
})->with([
    'no source' => [['source' => null], 'source'],
    'one figure' => [['items' => [['value' => '4', 'label' => 'centres']]], 'items'],
    'five figures' => [['items' => array_fill(0, 5, ['value' => '1', 'label' => 'x'])], 'items'],
    'long figure' => [['items' => [['value' => '1234567890123', 'label' => 'x'], ['value' => '2', 'label' => 'y']]], 'items.0.value'],
]);

it('needs a heading, two named options and a row before a comparison table is submitted', function (array $overrides, string $field) {
    expect(blockErrors(comparisonBlock($overrides)))->toHaveKey($field);
})->with([
    'no heading' => [['heading' => null], 'blocks.0.data.heading'],
    'one option' => [['column_2' => null], 'blocks.0.data.column_2'],
    'no rows' => [['rows' => []], 'blocks.0.data.rows'],
    'unknown field' => [['column_5' => 'Extra'], 'blocks.0.data'],
]);

it('renders figures and a comparison table as escaped text, without the figures\' source', function () {
    Page::factory()->published()->create(['slug' => 'compare', 'blocks' => [
        statsBlock(['items' => [['value' => '4', 'label' => '<script>alert(1)</script>'], ['value' => '24/7', 'label' => 'access']]]),
        comparisonBlock(),
    ]]);

    get('/compare')
        ->assertOk()
        ->assertSee('&lt;script&gt;', false)
        ->assertDontSee('<script>alert(1)</script>', false)
        ->assertDontSee('Owner property list')
        ->assertSee('<th scope="col" class="p-4 font-display text-base font-semibold text-forest">Private cabin</th>', false)
        ->assertSee('<th scope="row" class="p-4 font-semibold text-ink">Best for</th>', false)
        ->assertSeeInOrder(['Individuals', 'Teams']);
});
