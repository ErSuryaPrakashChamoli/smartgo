<?php

namespace App\Filament\Clusters\Website\Resources\TwinModels;

use App\Filament\Clusters\Website\Resources\TwinModels\Pages\CreateTwinModel;
use App\Filament\Clusters\Website\Resources\TwinModels\Pages\ListTwinModels;
use App\Filament\Clusters\Website\Resources\TwinModels\Pages\ViewTwinModel;
use App\Filament\Clusters\Website\Resources\TwinModels\Schemas\TwinModelForm;
use App\Filament\Clusters\Website\Resources\TwinModels\Schemas\TwinModelInfolist;
use App\Filament\Clusters\Website\Resources\TwinModels\Tables\TwinModelsTable;
use App\Filament\Clusters\Website\WebsiteCluster;
use App\Models\TwinModel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

/**
 * 3D models of the properties (FR-3D-1, ADR-0009): GLB files from the 3D-modelling supplier, checked at
 * upload, with the anchors found in them and a preview that loads only when asked for.
 */
class TwinModelResource extends Resource
{
    protected static ?string $model = TwinModel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static ?string $cluster = WebsiteCluster::class;

    protected static ?string $navigationLabel = '3D models';

    protected static ?string $modelLabel = '3D model';

    protected static ?string $recordTitleAttribute = 'title';

    protected static bool $isGloballySearchable = false;

    protected static ?int $navigationSort = 10;

    public static function form(Schema $schema): Schema
    {
        return TwinModelForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return TwinModelInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TwinModelsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTwinModels::route('/'),
            'create' => CreateTwinModel::route('/create'),
            'view' => ViewTwinModel::route('/{record}'),
        ];
    }
}
