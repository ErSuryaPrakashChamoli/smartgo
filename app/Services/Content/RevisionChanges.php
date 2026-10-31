<?php

namespace App\Services\Content;

use Illuminate\Support\Str;

/**
 * What a version changes compared with the live one, for the approver (UJ-8 "diff/preview"): each
 * field whose value differs, with its value before and after, as plain text. Sections are compared by
 * position and named by their number and kind, so a section added, removed or replaced is reported as
 * such. Read-only; it never changes a version.
 */
class RevisionChanges
{
    /**
     * The longest value shown; longer text is cut on a word boundary.
     */
    public const int VALUE_LIMIT = 300;

    /**
     * Names for fields shared by the content types; others are named from their keys.
     *
     * @var array<string, string>
     */
    private const array LABELS = [
        'title' => 'Title', 'slug' => 'Address', 'excerpt' => 'Summary', 'template' => 'Layout',
        'legal_review_reference' => 'Legal review reference', 'seo.title' => 'Title in search results',
        'seo.description' => 'Description in search results', 'seo.indexable' => 'Search engines may index it',
        'seo.image_asset_id' => 'Image when shared', 'question' => 'Question', 'answer' => 'Answer',
        'sort_order' => 'Position', 'topic_ids' => 'Topics', 'property_id' => 'Location', 'product_id' => 'Workspace type',
        'headline' => 'Headline', 'summary' => 'Summary', 'hero_asset_id' => 'Main photo', 'gallery_asset_ids' => 'Photos',
    ];

    public function __construct(private BlockLibrary $blockLibrary) {}

    /**
     * @param  array<string, mixed>  $before  the live version's payload
     * @param  array<string, mixed>  $after  the version being reviewed
     * @return list<array{field: string, before: string, after: string}>
     */
    public function between(array $before, array $after): array
    {
        $changes = $this->compare($this->flatten(array_diff_key($before, ['blocks' => true])), $this->flatten(array_diff_key($after, ['blocks' => true])), fn (string $key): string => self::LABELS[$key] ?? $this->name($key));

        if (array_key_exists('blocks', $before) || array_key_exists('blocks', $after)) {
            $changes = [...$changes, ...$this->sections(is_array($before['blocks'] ?? null) ? array_values($before['blocks']) : [], is_array($after['blocks'] ?? null) ? array_values($after['blocks']) : [])];
        }

        return $changes;
    }

    /**
     * @param  list<mixed>  $before
     * @param  list<mixed>  $after
     * @return list<array{field: string, before: string, after: string}>
     */
    private function sections(array $before, array $after): array
    {
        $changes = [];

        for ($index = 0; $index < max(count($before), count($after)); $index++) {
            $old = is_array($before[$index] ?? null) ? $before[$index] : null;
            $new = is_array($after[$index] ?? null) ? $after[$index] : null;
            $section = 'Section '.($index + 1);

            if ($old === null || $new === null || ($old['type'] ?? null) !== ($new['type'] ?? null)) {
                $changes[] = ['field' => $section, 'before' => $old === null ? '—' : $this->kind($old), 'after' => $new === null ? 'Removed' : $this->kind($new)];

                continue;
            }

            $label = $section.' ('.$this->kind($new).')';
            $changes = [...$changes, ...$this->compare(
                $this->flatten(is_array($old['data'] ?? null) ? $old['data'] : []),
                $this->flatten(is_array($new['data'] ?? null) ? $new['data'] : []),
                fn (string $key): string => $label.': '.Str::lcfirst($this->name($key)),
            )];
        }

        return $changes;
    }

    /**
     * @param  array<string, mixed>  $before
     * @param  array<string, mixed>  $after
     * @param  callable(string): string  $label
     * @return list<array{field: string, before: string, after: string}>
     */
    private function compare(array $before, array $after, callable $label): array
    {
        $changes = [];

        foreach (array_keys($before + $after) as $key) {
            $old = $this->text($before[$key] ?? null);
            $new = $this->text($after[$key] ?? null);

            if ($old !== $new) {
                $changes[] = ['field' => $label((string) $key), 'before' => $old, 'after' => $new];
            }
        }

        return $changes;
    }

    /**
     * Nested values as dotted keys; a list of plain values stays one value ("3, 5").
     *
     * @return array<string, mixed>
     */
    private function flatten(array $values, string $prefix = ''): array
    {
        $flat = [];

        foreach ($values as $key => $value) {
            $path = $prefix === '' ? (string) $key : $prefix.'.'.$key;

            if (is_array($value) && $value !== [] && ! (array_is_list($value) && array_filter($value, 'is_array') === [])) {
                $flat = [...$flat, ...$this->flatten($value, $path)];
            } else {
                $flat[$path] = $value;
            }
        }

        return $flat;
    }

    private function text(mixed $value): string
    {
        $text = match (true) {
            $value === null, $value === '', $value === [] => '—',
            is_bool($value) => $value ? 'Yes' : 'No',
            is_array($value) => implode(', ', array_map(fn (mixed $item): string => is_scalar($item) ? (string) $item : '', $value)),
            default => trim((string) preg_replace('/\s+/', ' ', strip_tags(str_replace(['</p>', '<br>', '<br/>', '<br />'], ' ', (string) $value)))),
        };

        return Str::limit($text === '' ? '—' : $text, self::VALUE_LIMIT, preserveWords: true);
    }

    /**
     * "items.0.question" → "Item 1 question".
     */
    private function name(string $key): string
    {
        $key = preg_replace('/_ids?$/', '', $key) ?? $key;
        $key = preg_replace_callback('/([a-z_]+)\.(\d+)/', fn (array $match): string => Str::singular($match[1]).' '.((int) $match[2] + 1), $key) ?? $key;

        return Str::ucfirst(trim(str_replace(['.', '_'], ' ', $key)));
    }

    /**
     * @param  array<string, mixed>  $block
     */
    private function kind(array $block): string
    {
        return $this->blockLibrary->find($block['type'] ?? null)?->label() ?? 'Section';
    }
}
