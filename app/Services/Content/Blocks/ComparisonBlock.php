<?php

namespace App\Services\Content\Blocks;

use Filament\Forms\Components\Builder\Block;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Support\Icons\Heroicon;

/**
 * A plain-text table comparing two to four options, row by row. Only options with a name are shown.
 * Cells are text only; prices and availability come from the pricing and inventory data in later
 * phases, never from this table.
 */
class ComparisonBlock implements ContentBlock
{
    public const int MAX_COLUMNS = 4;

    public const int MAX_ROWS = 12;

    public function type(): string
    {
        return 'comparison';
    }

    public function label(): string
    {
        return 'Comparison table';
    }

    public function editorBlock(): Block
    {
        $cells = fn (string $field, string $label, bool $markFirstTwo): array => array_map(
            fn (int $number): TextInput => TextInput::make("{$field}_{$number}")->label("{$label} {$number}")->maxLength($field === 'column' ? 40 : 80)->markAsRequired($markFirstTwo && $number <= 2),
            range(1, self::MAX_COLUMNS),
        );

        return Block::make($this->type())
            ->label($this->label())
            ->icon(Heroicon::OutlinedTableCells)
            ->schema([
                TextInput::make('heading')->markAsRequired()->maxLength(120)->helperText('Also read out as the table\'s caption.'),
                Textarea::make('intro')->label('Introduction')->rows(2)->maxLength(300),
                Grid::make(self::MAX_COLUMNS)->schema($cells('column', 'Option', true)),
                Repeater::make('rows')
                    ->label('Rows')
                    ->schema([
                        TextInput::make('label')->label('Row')->markAsRequired()->maxLength(80),
                        Grid::make(self::MAX_COLUMNS)->schema($cells('cell', 'Option', false)),
                    ])
                    ->helperText('Leave the options you do not use empty.')
                    ->maxItems(self::MAX_ROWS)
                    ->defaultItems(1)
                    ->addActionLabel('Add row'),
            ]);
    }

    public function rules(string $prefix, bool $forSubmission): array
    {
        $required = $forSubmission ? 'required' : 'nullable';
        $columns = range(1, self::MAX_COLUMNS);

        return [
            $prefix => ['array:heading,intro,rows,'.implode(',', array_map(fn (int $number): string => "column_{$number}", $columns))],
            "{$prefix}.heading" => [$required, 'string', 'max:120'],
            "{$prefix}.intro" => ['nullable', 'string', 'max:300'],
            ...collect($columns)->mapWithKeys(fn (int $number): array => ["{$prefix}.column_{$number}" => [$number <= 2 ? $required : 'nullable', 'string', 'max:40']])->all(),
            "{$prefix}.rows" => [$required, 'array', 'max:'.self::MAX_ROWS, ...($forSubmission ? ['min:1'] : [])],
            "{$prefix}.rows.*" => ['array:label,'.implode(',', array_map(fn (int $number): string => "cell_{$number}", $columns))],
            "{$prefix}.rows.*.label" => [$required, 'string', 'max:80'],
            ...collect($columns)->mapWithKeys(fn (int $number): array => ["{$prefix}.rows.*.cell_{$number}" => ['nullable', 'string', 'max:80']])->all(),
        ];
    }

    public function normalize(array $data): array
    {
        return [...$data, 'rows' => array_values($data['rows'] ?? [])];
    }

    public function present(array $data): array
    {
        $columns = array_values(array_filter(range(1, self::MAX_COLUMNS), fn (int $number): bool => filled($data["column_{$number}"] ?? null)));

        return [
            'heading' => (string) ($data['heading'] ?? ''),
            'intro' => $data['intro'] ?? null,
            'columns' => array_map(fn (int $number): string => (string) $data["column_{$number}"], $columns),
            'rows' => array_values(array_map(fn (array $row): array => [
                'label' => (string) ($row['label'] ?? ''),
                'cells' => array_map(fn (int $number): string => (string) ($row["cell_{$number}"] ?? ''), $columns),
            ], $data['rows'] ?? [])),
        ];
    }

    public function component(): string
    {
        return 'blocks.comparison';
    }
}
