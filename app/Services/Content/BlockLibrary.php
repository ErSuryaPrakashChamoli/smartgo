<?php

namespace App\Services\Content;

use App\Services\Content\Blocks\CallToActionBlock;
use App\Services\Content\Blocks\ContentBlock;
use App\Services\Content\Blocks\FaqBlock;
use App\Services\Content\Blocks\FeatureGridBlock;
use App\Services\Content\Blocks\HeroBlock;
use App\Services\Content\Blocks\TextBlock;
use Closure;
use Filament\Forms\Components\Builder\Block;
use Illuminate\Validation\Rule;

/**
 * The fixed set of page sections editors can use (FR-CMS-1). A new kind of section is added by
 * writing a ContentBlock and listing it here; nothing else is accepted in a payload.
 *
 * Not offered yet: images (step 4.4, media library with origin and licence, BR-MED-1 to BR-MED-4),
 * testimonials (step 4.5, consent on file, BR-CMS-7), workspace and property cards (Phase 5 data),
 * and enquiry forms (Phase 6). Nothing in a block may invent prices, availability or claims.
 */
class BlockLibrary
{
    public const int MAX_BLOCKS = 30;

    /**
     * @var list<class-string<ContentBlock>>
     */
    private const BLOCKS = [
        HeroBlock::class,
        TextBlock::class,
        FeatureGridBlock::class,
        FaqBlock::class,
        CallToActionBlock::class,
    ];

    /**
     * @return array<string, ContentBlock>
     */
    public function all(): array
    {
        $blocks = [];

        foreach (self::BLOCKS as $class) {
            $block = app($class);
            $blocks[$block->type()] = $block;
        }

        return $blocks;
    }

    public function find(mixed $type): ?ContentBlock
    {
        return is_string($type) ? ($this->all()[$type] ?? null) : null;
    }

    /**
     * @return list<Block>
     */
    public function editorBlocks(): array
    {
        return array_values(array_map(fn (ContentBlock $block): Block => $block->editorBlock(), $this->all()));
    }

    /**
     * Rules and readable field names for a list of blocks stored under `blocks`.
     *
     * @param  list<mixed>  $blocks
     * @return array{rules: array<string, mixed>, attributes: array<string, string>}
     */
    public function rules(array $blocks, bool $forSubmission): array
    {
        $rules = [
            'blocks' => [$forSubmission ? 'required' : 'present', 'array', 'list', 'max:'.self::MAX_BLOCKS, $this->introductionFirstRule()],
            'blocks.*' => ['required', 'array:type,data'],
            'blocks.*.type' => ['required', 'string', Rule::in(array_keys($this->all()))],
            'blocks.*.data' => ['present', 'array'],
        ];
        $attributes = [];

        foreach ($blocks as $index => $block) {
            $definition = $this->find(is_array($block) ? ($block['type'] ?? null) : null);

            if ($definition === null) {
                continue;
            }

            $prefix = "blocks.{$index}.data";
            $rules = [...$rules, ...$definition->rules($prefix, $forSubmission)];

            foreach (array_keys($definition->rules($prefix, $forSubmission)) as $key) {
                $field = str_replace(['.*.', '_', '.'], [' ', ' ', ' '], substr($key, strlen($prefix) + 1));
                $attributes[$key] = trim('section '.($index + 1).' ('.$definition->label().') '.$field);
            }
        }

        return ['rules' => $rules, 'attributes' => $attributes];
    }

    /**
     * @param  list<array{type: string, data: array<string, mixed>}>  $blocks
     * @return list<array{type: string, data: array<string, mixed>}>
     */
    public function normalize(array $blocks): array
    {
        return array_map(fn (array $block): array => [
            'type' => $block['type'],
            'data' => $this->find($block['type'])?->normalize($block['data']) ?? [],
        ], $blocks);
    }

    /**
     * The blocks ready for their views. Unknown types are skipped rather than rendered.
     *
     * @param  list<mixed>  $blocks
     * @return list<array{component: string, type: string, data: array<string, mixed>}>
     */
    public function present(array $blocks): array
    {
        $presented = [];

        foreach ($blocks as $block) {
            $definition = $this->find(is_array($block) ? ($block['type'] ?? null) : null);

            if ($definition !== null && is_array($block['data'] ?? null)) {
                $presented[] = ['component' => $definition->component(), 'type' => $definition->type(), 'data' => $definition->present($block['data'])];
            }
        }

        return $presented;
    }

    /**
     * The introduction carries the page's level-1 heading, so it may appear once, first.
     */
    private function introductionFirstRule(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! is_array($value)) {
                return;
            }

            $introductions = array_keys(array_map(fn (mixed $block): mixed => is_array($block) ? ($block['type'] ?? null) : null, $value), 'hero', true);

            if (count($introductions) > 1 || ($introductions !== [] && $introductions[0] !== 0)) {
                $fail('The introduction can be used once, as the first section.');
            }
        };
    }
}
