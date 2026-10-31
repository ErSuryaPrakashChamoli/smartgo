<?php

namespace App\Filament\Clusters\Sales\Resources\Tours;

use App\Enums\Booking\TourStatus;
use App\Filament\Clusters\Sales\Resources\Tours\Pages\ListTours;
use App\Filament\Clusters\Sales\Resources\Tours\Pages\ViewTour;
use App\Filament\Clusters\Sales\Resources\Tours\Schemas\TourInfolist;
use App\Filament\Clusters\Sales\Resources\Tours\Tables\ToursTable;
use App\Filament\Clusters\Sales\SalesCluster;
use App\Models\TourRequest;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * Tours (FR-BK-2, the staff side of FR-BK-5 for tours): visitors' requests waiting for confirmation and
 * the tours arranged, within each person's property scope (§3.1 "Tours"). Requests are kept apart from
 * confirmed tours. Tours are arranged from an enquiry or the website, never created bare, and never
 * deleted. Hidden on a database without the tour tables.
 */
class TourResource extends Resource
{
    protected static ?string $model = TourRequest::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $cluster = SalesCluster::class;

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Tours';

    protected static ?string $modelLabel = 'tour';

    protected static ?string $pluralModelLabel = 'tours';

    protected static ?string $slug = 'tours';

    protected static ?string $recordTitleAttribute = 'reference';

    protected static bool $isGloballySearchable = false;

    public static function canAccess(): bool
    {
        return TourRequest::isAvailable() && parent::canAccess();
    }

    /**
     * @return Builder<TourRequest>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = auth()->user();

        return parent::getEloquentQuery()->visibleTo($user);
    }

    /**
     * Requests waiting for confirmation, within the user's scope.
     */
    public static function getNavigationBadge(): ?string
    {
        if (! TourRequest::isAvailable()) {
            return null;
        }

        $count = static::getEloquentQuery()->where('status', TourStatus::Requested)->where('scheduled_start_at', '>', now())->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeTooltip(): ?string
    {
        return 'Tour requests waiting for confirmation';
    }

    public static function infolist(Schema $schema): Schema
    {
        return TourInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ToursTable::configure($table);
    }

    /**
     * @param  TourRequest  $record
     */
    public static function getRecordTitle(?Model $record): string
    {
        return $record === null ? 'Tour' : $record->reference.': SmartGro '.$record->property->name;
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTours::route('/'),
            'view' => ViewTour::route('/{record}'),
        ];
    }
}
