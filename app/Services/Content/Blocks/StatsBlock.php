<?php

namespace App\Services\Content\Blocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Support\Icons\Heroicon;

/**
 * Two to four figures with a short label each. Every figure must be real: the author records where
 * it comes from, for the approver only (it is never shown), and nothing here may invent a price,
 * availability or claim.
 */
class StatsBlock implements ContentBlock
{
    public function type(): string
    {
        return 'stats';
    }

    public function label(): string
    {
        return 'Figures';
    }

    public function editorBlock(): Block
    {
        return Block::make($this->type())
            ->label($this->label())
            ->icon(Heroicon::OutlinedChartBar)
            ->schema([
                TextInput::make('heading')->maxLength(120),
                Repeater::make('items')
                    ->label('Figures')
                    ->schema([
                        TextInput::make('value')->label('Figure')->markAsRequired()->maxLength(12)->placeholder('4'),
                        TextInput::make('label')->markAsRequired()->maxLength(80)->placeholder('centres in Noida'),
                    ])
                    ->columns(2)
                    ->helperText('Two to four figures.')
                    ->maxItems(4)
                    ->defaultItems(2)
                    ->addActionLabel('Add figure'),
                Textarea::make('source')
                    ->label('Where these figures come from')
                    ->markAsRequired()
                    ->rows(2)
                    ->maxLength(200)
                    ->helperText('For the approver only; not shown on the website. For example: the owner\'s property list of October 2026.'),
            ]);
    }

    public function rules(string $prefix, bool $forSubmission): array
    {
        $required = $forSubmission ? 'required' : 'nullable';

        return [
            $prefix => ['array:heading,items,source'],
            "{$prefix}.heading" => ['nullable', 'string', 'max:120'],
            "{$prefix}.items" => [$required, 'array', 'max:4', ...($forSubmission ? ['min:2'] : [])],
            "{$prefix}.items.*" => ['array:value,label'],
            "{$prefix}.items.*.value" => [$required, 'string', 'max:12'],
            "{$prefix}.items.*.label" => [$required, 'string', 'max:80'],
            "{$prefix}.source" => [$required, 'string', 'max:200'],
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
            'items' => array_values(array_map(
                fn (array $item): array => ['value' => (string) ($item['value'] ?? ''), 'label' => (string) ($item['label'] ?? '')],
                $data['items'] ?? [],
            )),
        ];
    }

    public function component(): string
    {
        return 'blocks.stats';
    }
}
