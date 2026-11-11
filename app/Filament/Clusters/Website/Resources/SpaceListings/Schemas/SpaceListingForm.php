<?php

namespace App\Filament\Clusters\Website\Resources\SpaceListings\Schemas;

use App\Models\MediaAsset;
use App\Models\Property;
use App\Models\PropertyProfile;
use App\Models\Space;
use App\Models\SpaceListing;
use App\Models\SpaceType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TagsInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * The space listing editor (OD-177). The rules on SpaceListing are the authority.
 */
class SpaceListingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Where it is')
                    ->description('The location and space type decide where the space appears in the menu: under its space type, with the other spaces at its location.')
                    ->columns(2)
                    ->schema([
                        Select::make('property_id')
                            ->label('Location')
                            ->options(fn (): array => Property::query()->with(['city', 'microMarket'])->orderBy('code')->get()
                                ->mapWithKeys(fn (Property $property): array => [$property->getKey() => 'SmartGro '.$property->name.' ('.collect([$property->microMarket?->name, $property->city?->name])->filter()->join(', ').')'])
                                ->all())
                            ->searchable()
                            ->markAsRequired()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('space_id', null)),
                        Select::make('space_type_id')
                            ->label('Space type')
                            ->options(fn (): array => SpaceType::query()->orderBy('sort_order')->orderBy('id')->pluck('name', 'id')->all())
                            ->markAsRequired()
                            ->live()
                            ->afterStateUpdated(fn (Set $set) => $set('space_id', null)),
                        Select::make('space_id')
                            ->label('Space in the inventory')
                            ->options(fn (Get $get): array => blank($get('property_id')) || blank($get('space_type_id')) ? [] : Space::query()
                                ->where('property_id', $get('property_id'))
                                ->where('space_type_id', $get('space_type_id'))
                                ->orderBy('code')
                                ->get(['id', 'code', 'name'])
                                ->mapWithKeys(fn (Space $space): array => [$space->getKey() => trim($space->code.' '.($space->name ?? ''))])
                                ->all())
                            ->placeholder('None')
                            ->helperText('Optional: the space in the inventory (Properties → Spaces) that this listing describes, at the same location and of the same type. The inventory is not changed.')
                            ->visible(fn (): bool => Space::isAvailable())
                            ->columnSpanFull(),
                    ]),
                Section::make('What visitors see')
                    ->description('Shown on the space\'s own page. Write only what SmartGro has confirmed, and no prices or availability.')
                    ->columns(4)
                    ->schema([
                        TextInput::make('name')
                            ->label('Name')
                            ->required()
                            ->maxLength(80)
                            ->helperText('As it appears in the menu, for example "Cabin 3" or "Meeting room M2".')
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Get $get, Set $set, ?string $state): void {
                                if (blank($get('slug')) && filled($state)) {
                                    $property = Property::query()->find($get('property_id'));
                                    $set('slug', Str::slug(collect([$property?->code, $state])->filter()->join(' ')));
                                }
                            })
                            ->columnSpan(2),
                        TextInput::make('slug')
                            ->label('Web address')
                            ->prefix('/spaces/')
                            ->required()
                            ->maxLength(120)
                            ->helperText('Filled in from the location and name; lowercase letters, numbers and hyphens.')
                            ->columnSpan(2),
                        Textarea::make('summary')
                            ->label('Summary')
                            ->required()
                            ->maxLength(200)
                            ->rows(2)
                            ->helperText('One or two sentences, under the name on the page and in search results.')
                            ->columnSpanFull(),
                        Textarea::make('details')
                            ->label('Details')
                            ->maxLength(3000)
                            ->rows(6)
                            ->helperText('Optional, plain text. Leave a blank line between paragraphs.')
                            ->columnSpanFull(),
                        Select::make('media_asset_id')
                            ->label('Photo')
                            ->options(fn (Get $get, mixed $state): array => self::photoOptions($get('property_id'), $state))
                            ->searchable()
                            ->placeholder('No photo')
                            ->helperText('Optional. Photography of this location from the media library; while sample content is allowed, also a stock photo, which the page labels as not the location.')
                            ->visible(fn (): bool => SpaceListing::hasPhotos())
                            ->columnSpanFull(),
                        TagsInput::make('features')
                            ->label('Features')
                            ->placeholder('Add a feature and press Enter')
                            ->helperText('Optional, up to '.SpaceListing::MAX_FEATURES.' short lines, such as "Natural light" or "Lockable door".')
                            ->columnSpan(2),
                        TextInput::make('seats')
                            ->label('Seats')
                            ->integer()
                            ->minValue(1)
                            ->maxValue(9999)
                            ->helperText('Optional. Only if confirmed.'),
                        TextInput::make('sort_order')
                            ->label('Position')
                            ->integer()
                            ->minValue(0)
                            ->maxValue(9999)
                            ->default(0)
                            ->helperText('Lower numbers come first at its location.'),
                        Toggle::make('is_sample')
                            ->label('Example space')
                            ->helperText('Marks example content that SmartGro has not confirmed. The page and the menu then say so, and this website publishes it only where sample content is allowed. Untick it once the details are SmartGro\'s own.')
                            ->columnSpanFull(),
                    ]),
            ]);
    }

    /**
     * Photography of the location; stock photos too while sample content is allowed; and always the photo the space
     * holds now, so an editor sees what has to be replaced (as PropertyProfileForm, OD-176).
     *
     * @return array<int, string>
     */
    private static function photoOptions(mixed $propertyId, mixed $current): array
    {
        return MediaAsset::query()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $query) => $query->where('depicts_property_id', (int) $propertyId)->whereIn('origin', PropertyProfile::PHOTO_ORIGINS))
                ->orWhere(fn (Builder $query) => $query->whereIn('origin', PropertyProfile::SAMPLE_PHOTO_ORIGINS)->when(! config('backoffice.sample_content'), fn (Builder $query) => $query->whereKey((int) $current)))
                ->orWhereKey((int) $current))
            ->latest()->limit(500)->get()
            ->reject(fn (MediaAsset $asset): bool => $asset->isLicenceExpired() && $asset->getKey() !== (int) $current)
            ->mapWithKeys(fn (MediaAsset $asset): array => [$asset->getKey() => $asset->title.' ('.(in_array($asset->origin, PropertyProfile::SAMPLE_PHOTO_ORIGINS, true) ? 'stock photo, sample content only' : $asset->origin->label()).')'])
            ->all();
    }
}
