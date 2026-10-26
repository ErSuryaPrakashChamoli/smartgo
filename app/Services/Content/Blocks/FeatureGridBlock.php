<?php

namespace App\Services\Content\Blocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * Two to six short points shown as cards, with an optional heading and introduction.
 */
class FeatureGridBlock implements ContentBlock
{
    public function type(): string
    {
        return 'features';
    }

    public function label(): string
    {
        return 'Feature grid';
    }

    public function editorBlock(): Block
    {
        return Block::make($this->type())
            ->label($this->label())
            ->icon(Heroicon::OutlinedSquares2x2)
            ->schema([
                TextInput::make('heading')->maxLength(120),
                Textarea::make('intro')->label('Introduction')->rows(2)->maxLength(300),
                Repeater::make('items')
                    ->label('Points')
                    ->schema([
                        TextInput::make('title')->markAsRequired()->maxLength(60),
                        Textarea::make('text')->markAsRequired()->rows(2)->maxLength(240),
                    ])
                    ->columns(2)
                    ->helperText('Two to six points.')
                    ->maxItems(6)
                    ->defaultItems(2)
                    ->addActionLabel('Add point'),
            ]);
    }

    public function rules(string $prefix, bool $forSubmission): array
    {
        $required = $forSubmission ? 'required' : 'nullable';

        return [
            $prefix => ['array:heading,intro,items'],
            "{$prefix}.heading" => ['nullable', 'string', 'max:120'],
            "{$prefix}.intro" => ['nullable', 'string', 'max:300'],
            "{$prefix}.items" => [$required, 'array', 'max:6', ...($forSubmission ? ['min:2'] : [])],
            "{$prefix}.items.*" => ['array:title,text'],
            "{$prefix}.items.*.title" => [$required, 'string', 'max:60'],
            "{$prefix}.items.*.text" => [$required, 'string', 'max:240'],
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
            'items' => array_values(array_map(
                fn (array $item): array => ['title' => (string) ($item['title'] ?? ''), 'text' => (string) ($item['text'] ?? '')],
                $data['items'] ?? [],
            )),
        ];
    }

    public function component(): string
    {
        return 'blocks.features';
    }
}
