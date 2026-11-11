<?php

namespace App\Filament\Clusters\Website\Resources\SpaceListings;

use App\Filament\Clusters\Website\Resources\Pages\RelationManagers\RevisionsRelationManager;
use App\Filament\Clusters\Website\Resources\SpaceListings\Pages\CreateSpaceListing;
use App\Filament\Clusters\Website\Resources\SpaceListings\Pages\EditSpaceListing;
use App\Filament\Clusters\Website\Resources\SpaceListings\Pages\ListSpaceListings;
use App\Filament\Clusters\Website\Resources\SpaceListings\Schemas\SpaceListingForm;
use App\Filament\Clusters\Website\Resources\SpaceListings\Tables\SpaceListingsTable;
use App\Filament\Clusters\Website\WebsiteCluster;
use App\Models\SpaceListing;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

/**
 * The spaces listed under Spaces in the website's main menu, each with its own page (OD-177), switched on or off
 * one by one. Not the inventory (Properties → Spaces), which this screen never changes.
 */
class SpaceListingResource extends Resource
{
    protected static ?string $model = SpaceListing::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $cluster = WebsiteCluster::class;

    protected static ?string $navigationLabel = 'Spaces on the website';

    protected static ?string $modelLabel = 'space';

    protected static ?string $pluralModelLabel = 'spaces on the website';

    protected static ?int $navigationSort = 2;

    protected static bool $isGloballySearchable = false;

    /**
     * Hidden on a database not migrated for space listings (OD-177).
     */
    public static function canAccess(): bool
    {
        return SpaceListing::isAvailable() && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        return SpaceListingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SpaceListingsTable::configure($table);
    }

    /**
     * @param  SpaceListing  $record
     */
    public static function getRecordTitle(?Model $record): string
    {
        return $record?->name ?? $record?->openRevision()?->payload['name'] ?? 'New space';
    }

    public static function getRelations(): array
    {
        return [
            RevisionsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSpaceListings::route('/'),
            'create' => CreateSpaceListing::route('/create'),
            'edit' => EditSpaceListing::route('/{record}/edit'),
        ];
    }
}
