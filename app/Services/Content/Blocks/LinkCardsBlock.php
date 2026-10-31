<?php

namespace App\Services\Content\Blocks;

use App\Services\Content\ContentLink;
use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * Two to nine cards, each with a title, a short text and an optional link to a page, a part of the
 * website or another website: the entry points of a hub page, such as the solutions for each kind of
 * customer or the articles of a topic (OD-143). A card whose link is not live is shown without it.
 */
class LinkCardsBlock implements ContentBlock
{
    public const int MAX_CARDS = 9;

    public function __construct(private ContentLink $contentLink) {}

    public function type(): string
    {
        return 'cards';
    }

    public function label(): string
    {
        return 'Cards with links';
    }

    public function editorBlock(): Block
    {
        return Block::make($this->type())
            ->label($this->label())
            ->icon(Heroicon::OutlinedRectangleGroup)
            ->schema([
                TextInput::make('heading')->maxLength(120),
                Textarea::make('intro')->label('Introduction')->rows(2)->maxLength(300),
                Repeater::make('items')
                    ->label('Cards')
                    ->schema([
                        TextInput::make('title')->markAsRequired()->maxLength(60),
                        Textarea::make('text')->rows(2)->maxLength(240),
                        $this->contentLink->editor('link', 'Link (optional)', required: false),
                    ])
                    ->helperText('Two to nine cards.')
                    ->maxItems(self::MAX_CARDS)
                    ->defaultItems(2)
                    ->collapsible()
                    ->addActionLabel('Add card'),
            ]);
    }

    public function rules(string $prefix, bool $forSubmission): array
    {
        $required = $forSubmission ? 'required' : 'nullable';

        return [
            $prefix => ['array:heading,intro,items'],
            "{$prefix}.heading" => ['nullable', 'string', 'max:120'],
            "{$prefix}.intro" => ['nullable', 'string', 'max:300'],
            "{$prefix}.items" => [$required, 'array', 'max:'.self::MAX_CARDS, ...($forSubmission ? ['min:2'] : [])],
            "{$prefix}.items.*" => ['array:title,text,link'],
            "{$prefix}.items.*.title" => [$required, 'string', 'max:60'],
            "{$prefix}.items.*.text" => ['nullable', 'string', 'max:240'],
            ...$this->contentLink->rules("{$prefix}.items.*.link", required: false, forSubmission: $forSubmission),
        ];
    }

    public function normalize(array $data): array
    {
        return [...$data, 'items' => array_values($data['items'] ?? [])];
    }

    public function present(array $data): array
    {
        return [
            'heading' => $data['heading'] ?? null,
            'intro' => $data['intro'] ?? null,
            'items' => array_values(array_map(fn (array $item): array => [
                'title' => (string) ($item['title'] ?? ''),
                'text' => filled($item['text'] ?? null) ? (string) $item['text'] : null,
                'link' => $this->contentLink->present($item['link'] ?? null),
            ], $data['items'] ?? [])),
        ];
    }

    public function component(): string
    {
        return 'blocks.cards';
    }
}
