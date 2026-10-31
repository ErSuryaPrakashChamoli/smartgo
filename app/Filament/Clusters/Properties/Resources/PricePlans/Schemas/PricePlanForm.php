<?php

namespace App\Filament\Clusters\Properties\Resources\PricePlans\Schemas;

use App\Enums\Pricing\DepositType;
use App\Enums\Pricing\PriceUnit;
use App\Models\Offering;
use App\Models\Property;
use App\Models\Space;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

/**
 * Finance's form for a draft price plan (SavePricePlan). Amounts are typed in rupees; the units offered
 * are those the chosen category may be priced in (config/pricing.php). Nothing is filled in for Finance.
 */
class PricePlanForm
{
    public static function configure(Schema $schema): Schema
    {
        $offering = fn (Get $get): ?Offering => Offering::query()->with('product')->find($get('offering_id'));
        $rupees = fn (string $name, string $label): TextInput => TextInput::make($name)->label($label)->prefix('₹')->maxLength(14)
            ->regex('/^\d{1,10}(\.\d{1,2})?$/')->validationMessages(['regex' => 'Enter an amount in rupees, such as 12500 or 12500.50.']);

        return $schema->columns(1)->components([
            Section::make('What is priced')->columns(2)->schema([
                Select::make('property_id')->label('Property')->dehydrated(false)->required()->live()
                    ->options(fn (): array => Property::query()->orderBy('name')->get()->mapWithKeys(fn (Property $property): array => [$property->getKey() => 'SmartGro '.$property->name])->all())
                    ->afterStateUpdated(fn (Set $set) => $set('offering_id', null)),
                Select::make('offering_id')->label('Workspace category')->required()->live()
                    ->options(fn (Get $get): array => Offering::query()->with('product')->where('property_id', $get('property_id'))->where('is_active', true)->get()
                        ->mapWithKeys(fn (Offering $item): array => [$item->getKey() => $item->product->name])->all())
                    ->afterStateUpdated(function (Set $set): void {
                        $set('unit', null);
                        $set('space_id', null);
                    }),
                Select::make('space_id')->label('One space only (optional)')
                    ->options(fn (Get $get): array => Space::query()->whereHas('offerings', fn ($query) => $query->whereKey($get('offering_id')))->orderBy('code')->pluck('code', 'id')->all())
                    ->placeholder('Every space of this category here')
                    ->helperText('Price one cabin or room differently from the category\'s price here.'),
                Select::make('unit')->label('Unit')->required()->live()
                    ->options(fn (Get $get): array => collect(config('pricing.units_by_category.'.($offering($get)?->product->key ?? '-'), []))
                        ->mapWithKeys(fn (string $unit): array => [$unit => PriceUnit::from($unit)->label()])->all())
                    ->helperText(fn (Get $get): ?string => $offering($get) !== null && config('pricing.units_by_category.'.$offering($get)->product->key, []) === [] ? $offering($get)->product->name.' are priced by quotation, not by a price plan.' : null),
                TextInput::make('bundle_size')->label('Days in the bundle')->integer()->minValue(1)->maxValue(366)
                    ->visible(fn (Get $get): bool => $get('unit') === PriceUnit::BundleDays->value)->required(fn (Get $get): bool => $get('unit') === PriceUnit::BundleDays->value),
            ]),
            Section::make('Price')->description('Tax-exclusive, in Indian rupees. "+ GST" is shown wherever prices appear.')->columns(2)->schema([
                $rupees('amount', 'Price')->required(),
                TextInput::make('min_term_months')->label('Minimum term in months (optional)')->integer()->minValue(1)->maxValue(120),
                Select::make('deposit_type')->label('Security deposit (optional)')->options(collect(DepositType::cases())->mapWithKeys(fn (DepositType $type): array => [$type->value => $type->label()])->all())->live()->placeholder('Not set'),
                TextInput::make('deposit_months')->label('Deposit in months of the fee')->numeric()->minValue(0.01)->maxValue(99.99)
                    ->visible(fn (Get $get): bool => $get('deposit_type') === DepositType::Months->value)->required(fn (Get $get): bool => $get('deposit_type') === DepositType::Months->value),
                $rupees('deposit_amount', 'Deposit amount')
                    ->visible(fn (Get $get): bool => $get('deposit_type') === DepositType::Fixed->value)->required(fn (Get $get): bool => $get('deposit_type') === DepositType::Fixed->value),
                $rupees('setup_fee', 'One-time set-up fee (optional)'),
            ]),
            Section::make('When it applies')->columns(2)->schema([
                DatePicker::make('valid_from')->label('From')->required()->helperText('It cannot start before the day it is activated.'),
                DatePicker::make('valid_to')->label('Until (empty if open-ended)')->afterOrEqual('valid_from'),
            ]),
        ]);
    }
}
