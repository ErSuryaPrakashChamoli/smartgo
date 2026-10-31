<?php

namespace App\Filament\Clusters\Properties\Resources\PricePlans;

use App\Enums\Access\Capability;
use App\Enums\Pricing\PricePlanStatus;
use App\Filament\Clusters\Properties\PropertiesCluster;
use App\Filament\Clusters\Properties\Resources\PricePlans\Pages\CreatePricePlan;
use App\Filament\Clusters\Properties\Resources\PricePlans\Pages\EditPricePlan;
use App\Filament\Clusters\Properties\Resources\PricePlans\Pages\ListPricePlans;
use App\Filament\Clusters\Properties\Resources\PricePlans\Pages\ViewPricePlan;
use App\Filament\Clusters\Properties\Resources\PricePlans\Schemas\PricePlanForm;
use App\Models\PricePlan;
use App\Models\Property;
use App\Models\User;
use App\Services\Inventory\BusinessDay;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Spatie\Activitylog\Models\Activity;

/**
 * Price plans (FR-INV-4): Finance enters them, another authorised person activates them (BR-PR-2). Every
 * role that sees offerings sees them; Property Managers only their own properties'. Nothing here is shown
 * on the website. Hidden on a database without the pricing tables.
 */
class PricePlanResource extends Resource
{
    protected static ?string $model = PricePlan::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $cluster = PropertiesCluster::class;

    protected static ?string $navigationLabel = 'Price plans';

    protected static ?int $navigationSort = 7;

    protected static bool $isGloballySearchable = false;

    public static function canAccess(): bool
    {
        return PricePlan::isAvailable() && parent::canAccess();
    }

    /**
     * @return Builder<PricePlan>
     */
    public static function getEloquentQuery(): Builder
    {
        /** @var User $user */
        $user = auth()->user();

        return parent::getEloquentQuery()->whereHas('offering.property', fn (Builder $property) => $property->visibleTo($user, Capability::ViewPrices));
    }

    public static function form(Schema $schema): Schema
    {
        return PricePlanForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Plan')->columns(2)->schema([
                TextEntry::make('scope')->label('Prices')->state(fn (PricePlan $record): string => $record->scopeName())->columnSpanFull(),
                TextEntry::make('unit')->state(fn (PricePlan $record): string => $record->unit->label().($record->bundle_size !== null ? ' ('.$record->bundle_size.' days)' : '')),
                TextEntry::make('amount')->label('Price')->state(fn (PricePlan $record): string => PricePlan::format($record->amount_minor).' + GST'),
                TextEntry::make('deposit')->label('Security deposit')->state(fn (PricePlan $record): string => match ($record->deposit_type?->value) {
                    'months' => $record->deposit_months.' months of the fee',
                    'fixed' => PricePlan::format($record->deposit_minor),
                    'none' => 'No deposit',
                    default => 'Not set',
                }),
                TextEntry::make('setup_fee')->label('Set-up fee')->state(fn (PricePlan $record): string => PricePlan::format($record->setup_fee_minor)),
                TextEntry::make('min_term')->label('Minimum term')->state(fn (PricePlan $record): string => $record->min_term_months !== null ? $record->min_term_months.' '.str('month')->plural($record->min_term_months) : 'Not set'),
                TextEntry::make('dates')->label('Applies')->state(fn (PricePlan $record): string => 'From '.$record->valid_from->format('j M Y').' '.($record->valid_to !== null ? 'to '.$record->valid_to->format('j M Y') : '(open-ended)')),
                TextEntry::make('state')->label('Status')->badge()->state(fn (PricePlan $record): string => $record->stateOn(BusinessDay::today())),
                TextEntry::make('creator.name')->label('Entered by'),
                TextEntry::make('approver')->label('Activated by')->state(fn (PricePlan $record): string => $record->approver?->name ?? 'Not activated'),
            ]),
            Section::make('History')->schema([
                View::make('filament.pricing.history')->viewData(fn (PricePlan $record): array => [
                    'entries' => Activity::query()->where('subject_type', $record->getMorphClass())->where('subject_id', $record->getKey())->with('causer')->latest('id')->get(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        $today = BusinessDay::today();

        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['offering.product', 'offering.property', 'space']))
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('scope')->label('Prices')->state(fn (PricePlan $record): string => $record->scopeName())->wrap(),
                TextColumn::make('unit')->state(fn (PricePlan $record): string => $record->unit->label()),
                TextColumn::make('amount_minor')->label('Price')->state(fn (PricePlan $record): string => PricePlan::format($record->amount_minor)),
                TextColumn::make('dates')->label('Applies')->state(fn (PricePlan $record): string => $record->valid_from->format('j M Y').' to '.($record->valid_to?->format('j M Y') ?? 'open-ended')),
                TextColumn::make('state')->label('Status')->badge()->state(fn (PricePlan $record): string => $record->stateOn($today))
                    ->color(fn (string $state): string => match ($state) {
                        'Current' => 'success', 'Scheduled' => 'info', 'Awaiting activation' => 'warning', default => 'gray',
                    }),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(PricePlanStatus::cases())->mapWithKeys(fn (PricePlanStatus $status): array => [$status->value => $status->label()])->all()),
                SelectFilter::make('property')->label('Property')
                    ->options(fn (): array => Property::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->query(fn (Builder $query, array $data) => filled($data['value'] ?? null) ? $query->whereHas('offering', fn (Builder $offering) => $offering->where('property_id', $data['value'])) : $query),
            ])
            ->emptyStateHeading('No price plans')
            ->emptyStateDescription('Finance enters each price; another authorised person activates it. No price is set until then.')
            ->recordActions([Action::make('open')->label('Open')->icon(Heroicon::OutlinedArrowRight)->url(fn (PricePlan $record): string => self::getUrl('view', ['record' => $record]))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPricePlans::route('/'),
            'create' => CreatePricePlan::route('/create'),
            'view' => ViewPricePlan::route('/{record}'),
            'edit' => EditPricePlan::route('/{record}/edit'),
        ];
    }
}
