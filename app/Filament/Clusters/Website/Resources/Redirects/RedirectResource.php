<?php

namespace App\Filament\Clusters\Website\Resources\Redirects;

use App\Filament\Clusters\Website\Resources\Redirects\Pages\ListRedirects;
use App\Filament\Clusters\Website\Resources\Redirects\Tables\RedirectsTable;
use App\Filament\Clusters\Website\WebsiteCluster;
use App\Models\Redirect;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

/**
 * The live redirects (FR-SEO-4), read-only: automatic ones follow their page (BR-CMS-6) and manual
 * ones change only by publishing the manual list (OD-66).
 */
class RedirectResource extends Resource
{
    protected static ?string $model = Redirect::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowUturnRight;

    protected static ?string $cluster = WebsiteCluster::class;

    protected static ?string $navigationLabel = 'Redirects';

    protected static string|UnitEnum|null $navigationGroup = 'Site setup';

    protected static ?string $modelLabel = 'redirect';

    protected static ?int $navigationSort = 9;

    protected static bool $isGloballySearchable = false;

    public static function table(Table $table): Table
    {
        return RedirectsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRedirects::route('/'),
        ];
    }
}
