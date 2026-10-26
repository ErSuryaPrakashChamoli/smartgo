<?php

namespace App\Filament\Clusters\Website\Resources\RedirectMaps;

use App\Filament\Clusters\Website\Resources\Pages\RelationManagers\RevisionsRelationManager;
use App\Filament\Clusters\Website\Resources\RedirectMaps\Pages\EditRedirectMap;
use App\Filament\Clusters\Website\Resources\RedirectMaps\Schemas\RedirectMapForm;
use App\Filament\Clusters\Website\WebsiteCluster;
use App\Models\RedirectMap;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;

/**
 * The list of redirects made by hand (FR-SEO-4, FR-SEO-6), reached from Website → Redirects.
 */
class RedirectMapResource extends Resource
{
    protected static ?string $model = RedirectMap::class;

    protected static ?string $cluster = WebsiteCluster::class;

    protected static ?string $modelLabel = 'manual redirects';

    protected static ?string $slug = 'manual-redirects';

    protected static bool $shouldRegisterNavigation = false;

    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return RedirectMapForm::configure($schema);
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
            'edit' => EditRedirectMap::route('/{record}/edit'),
        ];
    }
}
