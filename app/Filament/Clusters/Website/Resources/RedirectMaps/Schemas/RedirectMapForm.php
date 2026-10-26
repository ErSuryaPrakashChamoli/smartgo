<?php

namespace App\Filament\Clusters\Website\Resources\RedirectMaps\Schemas;

use App\Enums\Content\RedirectMatch;
use App\Services\Content\RedirectMapPayload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * The manual redirect list. RedirectMapPayload is the authority: paths on this website only, one
 * hop, no loops, and nothing another part of the website already answers.
 */
class RedirectMapForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Repeater::make('redirects')
                    ->label('Redirects made by hand')
                    ->helperText('Old addresses from the previous website and other moves. A page\'s own change of address is redirected automatically and is not listed here.')
                    ->schema([
                        TextInput::make('from_path')
                            ->label('Old address')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('/old-page'),
                        Select::make('match_type')
                            ->label('Matches')
                            ->options([RedirectMatch::Exact->value => 'This address only', RedirectMatch::Prefix->value => 'Every address starting with it'])
                            ->default(RedirectMatch::Exact->value)
                            ->selectablePlaceholder(false)
                            ->required(),
                        Select::make('status_code')
                            ->label('Answer')
                            ->options([301 => '301 moved permanently', 302 => '302 moved temporarily', 410 => '410 gone'])
                            ->default(301)
                            ->selectablePlaceholder(false)
                            ->required()
                            ->live(),
                        TextInput::make('to_path')
                            ->label('New address')
                            ->maxLength(255)
                            ->placeholder('/new-page')
                            ->disabled(fn (Get $get): bool => (int) $get('status_code') === 410)
                            ->markAsRequired(fn (Get $get): bool => (int) $get('status_code') !== 410),
                    ])
                    ->columns(4)
                    ->maxItems(RedirectMapPayload::MAX_REDIRECTS)
                    ->addActionLabel('Add redirect')
                    ->reorderable(false)
                    ->defaultItems(0),
            ]);
    }
}
