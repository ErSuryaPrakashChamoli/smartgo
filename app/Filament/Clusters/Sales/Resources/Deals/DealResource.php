<?php

namespace App\Filament\Clusters\Sales\Resources\Deals;

use App\Filament\Clusters\Sales\Resources\Deals\Pages\ListDeals;
use App\Filament\Clusters\Sales\Resources\Deals\Pages\ViewDeal;
use App\Filament\Clusters\Sales\Resources\Deals\RelationManagers\ProposedSpacesRelationManager;
use App\Filament\Clusters\Sales\Resources\Deals\Schemas\DealInfolist;
use App\Filament\Clusters\Sales\Resources\Deals\Tables\DealsTable;
use App\Filament\Clusters\Sales\SalesCluster;
use App\Models\Deal;
use App\Models\User;
use BackedEnum;
use Closure;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Deals opened from enquiries (FR-CRM-4): their stage, owner, values, proposed spaces and history. Each
 * member of staff sees only the deals of enquiries within their scope (BR-CRM-6); every page and list
 * starts from that scope, and every change is checked again on the server. Deals are opened from an
 * enquiry's page, never created blank here, and never deleted. Hidden until the deal tables exist.
 */
class DealResource extends Resource
{
    protected static ?string $model = Deal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBriefcase;

    protected static ?string $cluster = SalesCluster::class;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Deals';

    protected static ?string $modelLabel = 'deal';

    protected static ?string $slug = 'deals';

    protected static bool $isGloballySearchable = false;

    public static function canAccess(): bool
    {
        return Deal::isAvailable() && parent::canAccess();
    }

    /**
     * Filament loads a deal before it checks access, so a deal's own address is refused here first while
     * the deal tables are missing, rather than ending in a query error.
     */
    public static function resolveRecordRouteBinding(int|string $key, ?Closure $modifyQuery = null): ?Model
    {
        abort_unless(Deal::isAvailable(), 403);

        return parent::resolveRecordRouteBinding($key, $modifyQuery);
    }

    /**
     * @return Builder<Deal>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = auth()->user();

        return parent::getEloquentQuery()->visibleTo($user)->with(['lead.property', 'stage', 'owner']);
    }

    /**
     * @param  Deal|null  $record
     */
    public static function getRecordTitle(?Model $record): string
    {
        return $record === null ? 'Deal' : $record->title();
    }

    public static function infolist(Schema $schema): Schema
    {
        return DealInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return DealsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            ProposedSpacesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDeals::route('/'),
            'view' => ViewDeal::route('/{record}'),
        ];
    }
}
