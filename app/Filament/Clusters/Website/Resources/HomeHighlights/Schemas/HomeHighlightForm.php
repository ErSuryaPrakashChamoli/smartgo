<?php

namespace App\Filament\Clusters\Website\Resources\HomeHighlights\Schemas;

use App\Models\HomeHighlight;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The highlight editor. The rules on HomeHighlight are the authority.
 */
class HomeHighlightForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Highlight')
                    ->description('A short line about SmartGro\'s locations, such as a facility or a service. Write only what is true at every location shown, and no prices or availability.')
                    ->columns(4)
                    ->schema([
                        TextInput::make('text')
                            ->label('Text')
                            ->required()
                            ->maxLength(80)
                            ->columnSpan(3),
                        TextInput::make('sort_order')
                            ->label('Position')
                            ->integer()
                            ->minValue(0)
                            ->maxValue(9999)
                            ->default(0)
                            ->helperText('Lower numbers come first.'),
                        Select::make('icon')
                            ->label('Icon')
                            ->options(HomeHighlight::ICONS)
                            ->default(HomeHighlight::DEFAULT_ICON)
                            ->selectablePlaceholder(false)
                            ->columnSpan(2),
                        Toggle::make('is_sample')
                            ->label('Example text')
                            ->helperText('Marks text that SmartGro has not confirmed. The strip then says "Example highlights", and this website publishes it only where sample content is allowed. Untick it once the text is SmartGro\'s own.')
                            ->columnSpan(2),
                    ]),
            ]);
    }
}
