<?php

namespace App\Filament\Clusters\Website\Resources\Faqs\Schemas;

use App\Models\Faq;
use App\Models\Product;
use App\Models\Property;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * The FAQ editor. The rules on Faq are the authority. Where a question is shown is chosen from lists:
 * the FAQ page, one location's page or one workspace type's page (FR-CMS-7). "Shown on" itself is not
 * stored; the chosen location or workspace type is.
 */
class FaqForm
{
    public const array SHOWN_ON = [
        'faq_page' => 'The FAQ page (a general question)',
        'location' => 'One location\'s page',
        'workspace' => 'One workspace type\'s page',
    ];

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
                Section::make('Shown on')
                    ->description('A location\'s page shows its own questions first, then the general ones. A workspace type\'s page shows its own questions.')
                    ->columns(2)
                    ->visible(fn (): bool => Faq::hasScopes())
                    ->schema([
                        Radio::make('shown_on')
                            ->hiddenLabel()
                            ->options(self::SHOWN_ON)
                            ->default('faq_page')
                            ->live()
                            ->columnSpanFull(),
                        Select::make('property_id')
                            ->label('Location')
                            ->options(fn (): array => Property::query()->publiclyListed()->orderBy('code')->pluck('name', 'id')->map(fn (string $name): string => 'SmartGro '.$name)->all())
                            ->required()
                            ->helperText('Locations on the website.')
                            ->visible(fn (Get $get): bool => $get('shown_on') === 'location'),
                        Select::make('product_id')
                            ->label('Workspace type')
                            ->options(fn (): array => Product::query()->shown()->pluck('name', 'id')->all())
                            ->required()
                            ->visible(fn (Get $get): bool => $get('shown_on') === 'workspace'),
                    ]),
            ]);
    }

    /**
     * "Shown on" for a version's stored scope.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function shownOn(array $payload): string
    {
        return match (true) {
            filled($payload['property_id'] ?? null) => 'location',
            filled($payload['product_id'] ?? null) => 'workspace',
            default => 'faq_page',
        };
    }
}
