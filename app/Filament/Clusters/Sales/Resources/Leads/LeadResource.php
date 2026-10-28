<?php

namespace App\Filament\Clusters\Sales\Resources\Leads;

use App\Enums\Crm\LeadStatus;
use App\Filament\Clusters\Sales\Resources\Leads\Pages\ListLeads;
use App\Filament\Clusters\Sales\Resources\Leads\Pages\ViewLead;
use App\Filament\Clusters\Sales\Resources\Leads\RelationManagers\ActivitiesRelationManager;
use App\Filament\Clusters\Sales\Resources\Leads\Schemas\LeadInfolist;
use App\Filament\Clusters\Sales\Resources\Leads\Tables\LeadsTable;
use App\Filament\Clusters\Sales\SalesCluster;
use App\Models\Lead;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Website enquiries (BR-CRM-1, FR-CRM-1 in part). Each member of staff sees only the enquiries within
 * their scope (BR-CRM-6); every page, list, search, filter and export starts from that scope, and every
 * change is checked again on the server. Enquiries are never created, edited or deleted here.
 */
class LeadResource extends Resource
{
    protected static ?string $model = Lead::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInboxArrowDown;

    protected static ?string $cluster = SalesCluster::class;

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Enquiries';

    protected static ?string $modelLabel = 'enquiry';

    protected static ?string $pluralModelLabel = 'enquiries';

    protected static ?string $slug = 'enquiries';

    protected static ?string $recordTitleAttribute = 'reference';

    protected static bool $isGloballySearchable = false;

    /**
     * @return Builder<Lead>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = auth()->user();

        return parent::getEloquentQuery()->visibleTo($user);
    }

    /**
     * New enquiries without an owner, within the user's scope.
     */
    public static function getNavigationBadge(): ?string
    {
        $count = static::getEloquentQuery()->where('status', LeadStatus::New)->whereNull('owner_user_id')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'New enquiries without an owner';
    }

    public static function infolist(Schema $schema): Schema
    {
        return LeadInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return LeadsTable::configure($table);
    }

    /**
     * @param  Lead  $record
     */
    public static function getRecordTitle(?Model $record): string
    {
        return $record === null ? 'Enquiry' : $record->reference.': '.$record->submitted_name;
    }

    public static function getRelations(): array
    {
        return [
            ActivitiesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListLeads::route('/'),
            'view' => ViewLead::route('/{record}'),
        ];
    }
}
