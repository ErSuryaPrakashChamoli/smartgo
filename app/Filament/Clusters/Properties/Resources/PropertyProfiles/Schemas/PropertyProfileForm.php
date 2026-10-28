<?php

namespace App\Filament\Clusters\Properties\Resources\PropertyProfiles\Schemas;

use App\Models\MediaAsset;
use App\Models\Property;
use App\Models\PropertyProfile;
use Closure;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * The property profile form. Rules are in PropertyProfile; capacity, status, availability and price
 * are not here and cannot be changed from it (FR-CMS-6). The photo and search sections are shared with
 * the workspace offering form.
 */
class PropertyProfileForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Profile')
                    ->description('What the property page says above the address and workspace categories. Write only what SmartGro has confirmed.')
                    ->schema([
                        TextInput::make('headline')->markAsRequired()->maxLength(120),
                        Textarea::make('summary')->markAsRequired()->maxLength(600)->rows(4),
                    ]),
                Section::make('Getting here')
                    ->description('Optional. Only what SmartGro has confirmed for this location; empty fields are not shown. A map link appears when the building\'s coordinates are recorded.')
                    ->columns(2)
                    ->schema([
                        Textarea::make('getting_there')->label('Directions')->maxLength(600)->rows(3)->columnSpanFull()
                            ->helperText('How to find the entrance, landmarks or cab drop-off.'),
                        TextInput::make('nearest_metro')->label('Nearest metro station')->maxLength(80)
                            ->helperText('For example the station name and its line.'),
                        TextInput::make('metro_walk_minutes')->label('Walk from the metro (minutes)')->integer()->minValue(1)->maxValue(60),
                        TextInput::make('buses')->label('Buses')->maxLength(200)
                            ->helperText('Bus routes or the nearest stop.'),
                        TextInput::make('parking')->label('Parking')->maxLength(300),
                        Textarea::make('accessibility')->label('Accessibility')->maxLength(300)->rows(2)->columnSpanFull()
                            ->helperText('Step-free entrance, lifts, accessible toilets, as they actually are.'),
                    ]),
                Section::make('Contact details for this location')
                    ->description('Optional. Only a phone number, WhatsApp number or email SmartGro has confirmed for this location. Left empty, the page shows SmartGro\'s main contact details. They appear only once this version is approved and published.')
                    ->columns(3)
                    ->schema([
                        TextInput::make('phone')->label('Phone')->tel()->maxLength(20)->helperText('For example +91 120 000 0000.'),
                        TextInput::make('whatsapp')->label('WhatsApp number')->maxLength(16)->helperText('International format: + and the country code, digits only.'),
                        TextInput::make('email')->label('Email')->email()->maxLength(120),
                    ]),
                self::photoSection(fn (?PropertyProfile $record): ?Property => $record?->property),
                self::searchSection(),
            ]);
    }

    /**
     * Main photo and more photos, chosen only from the photos uploaded for the property.
     *
     * @param  Closure(mixed): ?Property  $property
     */
    public static function photoSection(Closure $property): Section
    {
        $options = fn (mixed $record): array => self::photoOptions($property($record));

        return Section::make('Photos')
            ->description('Only SmartGro or commissioned photography uploaded for this property can be chosen. Concept visuals, renders and stock photos are never shown as the property. Alt text comes from the media library. Replacing or removing a photo is a change to this profile, so it needs approval like any other.')
            ->schema([
                Select::make('hero_asset_id')
                    ->label('Main photo')
                    ->options($options)
                    ->searchable(),
                Select::make('gallery_asset_ids')
                    ->label('More photos')
                    ->multiple()
                    ->options($options)
                    ->maxItems(PropertyProfile::MAX_PHOTOS)
                    ->helperText('Up to '.PropertyProfile::MAX_PHOTOS.', shown in the order chosen. Add photos in Website → Media library, choosing this property.'),
            ]);
    }

    public static function searchSection(): Section
    {
        return Section::make('Search engines')
            ->description('Optional. Leave empty to use the page title and summary.')
            ->collapsible()
            ->schema([
                TextInput::make('seo_title')->label('Search title')->maxLength(70),
                Textarea::make('seo_description')->label('Search description')->maxLength(160)->rows(2),
            ]);
    }

    /**
     * @return array<int, string>
     */
    private static function photoOptions(?Property $property): array
    {
        if ($property === null) {
            return [];
        }

        return MediaAsset::query()->where('depicts_property_id', $property->getKey())->whereIn('origin', PropertyProfile::PHOTO_ORIGINS)->latest()->limit(500)->get()
            ->reject(fn (MediaAsset $asset): bool => $asset->isLicenceExpired())
            ->mapWithKeys(fn (MediaAsset $asset): array => [$asset->getKey() => $asset->title.' ('.$asset->origin->label().')'])
            ->all();
    }
}
