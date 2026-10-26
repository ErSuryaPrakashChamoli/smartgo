<?php

namespace App\Filament\Clusters\Website\Resources\Faqs\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The FAQ editor. The rules on Faq are the authority.
 */
class FaqForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('FAQ')
                    ->columns(4)
                    ->schema([
                        TextInput::make('question')
                            ->label('Question')
                            ->required()
                            ->maxLength(150)
                            ->columnSpan(3),
                        TextInput::make('sort_order')
                            ->label('Position')
                            ->integer()
                            ->minValue(0)
                            ->maxValue(9999)
                            ->default(0)
                            ->helperText('Lower numbers appear first.'),
                        Textarea::make('answer')
                            ->label('Answer')
                            ->markAsRequired()
                            ->maxLength(1500)
                            ->rows(5)
                            ->helperText('Plain text. Line breaks are kept. Do not state prices or availability here.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }
}
