<?php

namespace App\Filament\Clusters\Properties\Resources\OfferingProfiles\Schemas;

use App\Filament\Clusters\Properties\Resources\PropertyProfiles\Schemas\PropertyProfileForm;
use App\Models\OfferingProfile;
use App\Models\Property;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The workspace offering form. Rules are in OfferingProfile. There is no capacity, availability or
 * price here: those come from inventory and approved pricing, which marketing staff cannot change
 * (FR-CMS-6, BR-SOT-3).
 */
class OfferingProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Offering')
                    ->description('What this property offers in this workspace category. Write only what SmartGro has confirmed. Capacity, availability and prices are not shown: visitors are told they are confirmed on request.')
                    ->schema([
                        TextInput::make('headline')->markAsRequired()->maxLength(120),
                        Textarea::make('description')->markAsRequired()->maxLength(1200)->rows(5),
                        TagsInput::make('included')
                            ->label('Included')
                            ->placeholder('Add an item and press Enter')
                            ->helperText('Verified features and amenities only, up to '.OfferingProfile::MAX_LIST_ITEMS.' items of 80 characters.'),
                        TagsInput::make('not_included')
                            ->label('Not included')
                            ->placeholder('Add an item and press Enter')
                            ->helperText('What visitors should not expect, so nothing is implied.'),
                    ]),
                PropertyProfileForm::photoSection(fn (?OfferingProfile $record): ?Property => $record?->offering?->property),
                PropertyProfileForm::searchSection(),
            ]);
    }
}
