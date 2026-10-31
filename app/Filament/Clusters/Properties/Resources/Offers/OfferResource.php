<?php

namespace App\Filament\Clusters\Properties\Resources\Offers;

use App\Enums\Pricing\DiscountType;
use App\Filament\Clusters\Properties\PropertiesCluster;
use App\Filament\Clusters\Properties\Resources\Offers\Pages\CreateOffer;
use App\Filament\Clusters\Properties\Resources\Offers\Pages\EditOffer;
use App\Filament\Clusters\Properties\Resources\Offers\Pages\ListOffers;
use App\Filament\Clusters\Properties\Resources\Offers\Pages\ViewOffer;
use App\Models\Offer;
use App\Models\Offering;
use App\Models\PricePlan;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Activity;

/**
 * Promotional offers (FR-INV-5): drafted by Marketing, Finance or the Super Admin, approved by Finance
 * or the Super Admin when they did not draft them (BR-PR-5), expiring on their own. None is shown on the
 * website. Hidden on a database without the pricing tables.
 */
class OfferResource extends Resource
{
    protected static ?string $model = Offer::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedReceiptPercent;

    protected static ?string $cluster = PropertiesCluster::class;

    protected static ?string $navigationLabel = 'Offers';

    protected static ?int $navigationSort = 8;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = false;

    public static function canAccess(): bool
    {
        return PricePlan::isAvailable() && parent::canAccess();
    }

    public static function form(Schema $schema): Schema
    {
        $type = fn (Get $get): ?DiscountType => DiscountType::tryFrom((string) $get('discount_type'));

        return $schema->columns(1)->components([
            Section::make('Offer')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(120),
                TextInput::make('code')->label('Code (optional)')->maxLength(40)->helperText('Capital letters, numbers and hyphens, for example WELCOME-2027.'),
                Select::make('discount_type')->label('Discount')->required()->live()
                    ->options(collect(DiscountType::cases())->mapWithKeys(fn (DiscountType $item): array => [$item->value => $item->label()])->all()),
                TextInput::make('percent')->label('Percentage off')->numeric()->suffix('%')->minValue(0.01)->maxValue(100)
                    ->visible(fn (Get $get): bool => $type($get) === DiscountType::Percent)->required(fn (Get $get): bool => $type($get) === DiscountType::Percent),
                TextInput::make('amount')->label('Amount off')->prefix('₹')->maxLength(14)
                    ->visible(fn (Get $get): bool => $type($get) === DiscountType::Fixed)->required(fn (Get $get): bool => $type($get) === DiscountType::Fixed),
                TextInput::make('free_period_quantity')->label('Free period')->integer()->minValue(1)->maxValue(366)
                    ->visible(fn (Get $get): bool => $type($get) === DiscountType::FreePeriod)->required(fn (Get $get): bool => $type($get) === DiscountType::FreePeriod),
                Select::make('free_period_unit')->label('Free period unit')->options(['day' => 'Days', 'month' => 'Months'])
                    ->visible(fn (Get $get): bool => $type($get) === DiscountType::FreePeriod)->required(fn (Get $get): bool => $type($get) === DiscountType::FreePeriod),
                DateTimePicker::make('starts_at')->label('Starts')->required()->seconds(false)->helperText('India Standard Time.'),
                DateTimePicker::make('ends_at')->label('Ends')->required()->seconds(false)->after('starts_at')->helperText('India Standard Time. The offer stops applying then on its own.'),
                TextInput::make('max_redemptions')->label('Most uses (optional)')->integer()->minValue(1),
                CheckboxList::make('offering_ids')->label('Applies to')->required()->columnSpanFull()->columns(2)
                    ->options(fn (): array => Offering::query()->with(['product', 'property'])->where('is_active', true)->get()
                        ->sortBy(fn (Offering $offering): string => $offering->property->name.$offering->product->sort_order)
                        ->mapWithKeys(fn (Offering $offering): array => [$offering->getKey() => $offering->displayName()])->all()),
                Textarea::make('terms')->required()->maxLength(5000)->rows(4)->columnSpanFull()->helperText('Shown with the offer wherever it is used (BR-PR-5).'),
            ]),
        ]);
    }

    public static function infolist(Schema $schema): Schema
    {
        $timezone = config('backoffice.timezone');

        return $schema->columns(1)->components([
            Section::make('Offer')->columns(2)->schema([
                TextEntry::make('name'),
                TextEntry::make('code')->state(fn (Offer $record): string => $record->code ?? 'No code'),
                TextEntry::make('discount')->state(fn (Offer $record): string => $record->discountSummary()),
                TextEntry::make('state')->label('Status')->badge()->state(fn (Offer $record): string => $record->stateAt(Carbon::now())->label())
                    ->color(fn (Offer $record): string => $record->stateAt(Carbon::now())->color()),
                TextEntry::make('when')->label('Applies')->columnSpanFull()
                    ->state(fn (Offer $record): string => $record->starts_at->copy()->timezone($timezone)->format('j M Y, H:i').' to '.$record->ends_at->copy()->timezone($timezone)->format('j M Y, H:i T')),
                TextEntry::make('applies_to')->label('Applies to')->columnSpanFull()
                    ->state(fn (Offer $record): string => $record->offerings()->with(['product', 'property'])->get()->map(fn (Offering $offering): string => $offering->displayName())->implode('; ')),
                TextEntry::make('max_redemptions')->label('Most uses')->state(fn (Offer $record): string => $record->max_redemptions !== null ? (string) $record->max_redemptions : 'No limit'),
                TextEntry::make('terms')->columnSpanFull(),
            ]),
            Section::make('History')->schema([
                View::make('filament.pricing.history')->viewData(fn (Offer $record): array => [
                    'entries' => Activity::query()->where('subject_type', $record->getMorphClass())->where('subject_id', $record->getKey())->with('causer')->latest('id')->get(),
                ]),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('id', 'desc')
            ->columns([
                TextColumn::make('name')->weight('semibold')->description(fn (Offer $record): ?string => $record->code)->searchable(),
                TextColumn::make('discount')->state(fn (Offer $record): string => $record->discountSummary()),
                TextColumn::make('ends_at')->label('Ends')->dateTime('j M Y, H:i', config('backoffice.timezone')),
                TextColumn::make('state')->label('Status')->badge()
                    ->state(fn (Offer $record): string => $record->stateAt(Carbon::now())->label())
                    ->color(fn (Offer $record): string => $record->stateAt(Carbon::now())->color()),
            ])
            ->emptyStateHeading('No offers')
            ->emptyStateDescription('Offers are drafted with their terms and applied only after Finance or the Super Admin approves them.')
            ->recordActions([Action::make('open')->label('Open')->icon(Heroicon::OutlinedArrowRight)->url(fn (Offer $record): string => self::getUrl('view', ['record' => $record]))]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListOffers::route('/'),
            'create' => CreateOffer::route('/create'),
            'view' => ViewOffer::route('/{record}'),
            'edit' => EditOffer::route('/{record}/edit'),
        ];
    }

    /**
     * The form's values for an offer, with its instants in India Standard Time.
     *
     * @return array<string, mixed>
     */
    public static function formData(Offer $offer): array
    {
        $timezone = config('backoffice.timezone');

        return [
            ...$offer->only(['name', 'code', 'free_period_quantity', 'free_period_unit', 'max_redemptions', 'terms']),
            'discount_type' => $offer->discount_type->value,
            'percent' => $offer->percent_bps !== null ? $offer->percent_bps / 100 : null,
            'amount' => PricePlan::toRupees($offer->amount_minor),
            'starts_at' => $offer->starts_at->copy()->timezone($timezone)->format('Y-m-d H:i'),
            'ends_at' => $offer->ends_at->copy()->timezone($timezone)->format('Y-m-d H:i'),
            'offering_ids' => $offer->offerings()->pluck('offerings.id')->map(fn (int $id): string => (string) $id)->all(),
        ];
    }
}
