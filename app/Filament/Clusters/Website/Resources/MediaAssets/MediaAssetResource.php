<?php

namespace App\Filament\Clusters\Website\Resources\MediaAssets;

use App\Filament\Clusters\Website\Resources\MediaAssets\Pages\CreateMediaAsset;
use App\Filament\Clusters\Website\Resources\MediaAssets\Pages\EditMediaAsset;
use App\Filament\Clusters\Website\Resources\MediaAssets\Pages\ListMediaAssets;
use App\Filament\Clusters\Website\Resources\MediaAssets\Schemas\MediaAssetForm;
use App\Filament\Clusters\Website\Resources\MediaAssets\Tables\MediaAssetsTable;
use App\Filament\Clusters\Website\WebsiteCluster;
use App\Models\MediaAsset;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * The media library (FR-CMS-5, ADR-0006): images with origin, licence and descriptions.
 */
class MediaAssetResource extends Resource
{
    protected static ?string $model = MediaAsset::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPhoto;

    protected static ?string $cluster = WebsiteCluster::class;

    protected static ?string $navigationLabel = 'Media library';

    protected static ?string $modelLabel = 'image';

    protected static ?string $recordTitleAttribute = 'title';

    protected static bool $isGloballySearchable = false;

    protected static ?int $navigationSort = 3;

    public static function form(Schema $schema): Schema
    {
        return MediaAssetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MediaAssetsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMediaAssets::route('/'),
            'create' => CreateMediaAsset::route('/create'),
            'edit' => EditMediaAsset::route('/{record}/edit'),
        ];
    }
}
