<?php

namespace App\Filament\Clusters\Properties\Resources\Properties\Schemas;

use App\Actions\Properties\SaveProperty;
use App\Enums\Access\Capability;
use App\Enums\Properties\OperatingModel;
use App\Enums\Properties\PropertyStatus;
use App\Models\City;
use App\Models\MicroMarket;
use App\Models\Product;
use App\Models\Property;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Components\Fieldset;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

/**
 * The property form. SaveProperty holds the rules; these fields only help staff fill them in. Only
 * information SmartGro has confirmed belongs here: leave a field empty rather than guess.
 */
class PropertyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->columns(1)
            ->components([
                Section::make('Property')
                    ->columns(2)
                    ->schema([
                        TextInput::make('name')->required()->maxLength(80)->helperText('As customers know it, for example A-70.')->disabled(self::isOperationsOnly(...)),
                        TextInput::make('code')->required()->maxLength(20)->regex(SaveProperty::CODE_PATTERN)->helperText('Internal reference, for example A-70. Capital letters, numbers and hyphens.')->disabled(self::isOperationsOnly(...)),
                        Select::make('city_id')
                            ->label('City')
                            ->options(fn (): array => City::query()->orderBy('name')->pluck('name', 'id')->all())
                            ->required()
                            ->live()
                            ->disabled(self::isOperationsOnly(...)),
                        Select::make('micro_market_id')
                            ->label('Sector')
                            ->options(fn (Get $get): array => MicroMarket::query()->where('city_id', $get('city_id'))->orderBy('name')->pluck('name', 'id')->all())
                            ->helperText('Sectors are added under Cities.')
                            ->disabled(self::isOperationsOnly(...)),
                        TextInput::make('slug')
                            ->label('Website address')
                            ->prefix(fn (Get $get): string => url('/locations/'.(City::query()->find($get('city_id'))?->slug ?? '…')).'/')
                            ->required()
                            ->maxLength(80)
                            ->regex(SaveProperty::SLUG_PATTERN)
                            ->disabled(self::isOperationsOnly(...)),
                        Select::make('operating_model')
                            ->options(collect(OperatingModel::cases())->mapWithKeys(fn (OperatingModel $model): array => [$model->value => $model->label()])->all())
                            ->required()
                            ->helperText('Recorded, never assumed (OD-09).')
                            ->disabled(self::isOperationsOnly(...)),
                        Select::make('operational_status')
                            ->label('Status')
                            ->options(collect(PropertyStatus::cases())->mapWithKeys(fn (PropertyStatus $status): array => [$status->value => $status->label()])->all())
                            ->default(PropertyStatus::Planned->value)
                            ->required()
                            ->helperText('Operational state, set by operations staff. The property is on the website only while it is open (or planned or pre-opening with an opening date) and its website profile is published.')
                            ->disabled(self::isOperationsOnly(...)),
                        DatePicker::make('opens_on')->label('Opens on')->disabled(self::isOperationsOnly(...)),
                    ]),
                Section::make('Address')
                    ->description('The primary building. Leave the PIN code and coordinates empty until SmartGro confirms them.')
                    ->statePath('building')
                    ->columns(2)
                    ->schema([
                        TextInput::make('address_line_1')->label('Address line 1')->required()->maxLength(120)->helperText('For example A-70.'),
                        TextInput::make('address_line_2')->label('Address line 2')->maxLength(120),
                        TextInput::make('locality')->label('Sector or locality')->required()->maxLength(80),
                        TextInput::make('postal_code')->label('PIN code')->maxLength(6),
                        TextInput::make('state')->maxLength(60),
                        TextInput::make('name')->label('Building name')->maxLength(80),
                        TextInput::make('latitude')->numeric(),
                        TextInput::make('longitude')->numeric(),
                        TextInput::make('code')->label('Building code')->maxLength(30)->helperText('Optional; set from the property code when empty.')->disabled(self::isOperationsOnly(...)),
                    ]),
                Section::make('Opening hours')
                    ->description('Leave every day empty until SmartGro confirms the hours. Once any day has hours, the website shows days left empty as closed.')
                    ->statePath('operating_hours')
                    ->columns(['md' => 2, 'xl' => 4])
                    ->collapsible()
                    ->schema(collect(Property::WEEKDAYS)->map(fn (string $label, string $day): Fieldset => Fieldset::make($label)
                        ->statePath($day)
                        ->columns(2)
                        ->schema([
                            TimePicker::make('opens')->label('Opens')->seconds(false)->format('H:i'),
                            TimePicker::make('closes')->label('Closes')->seconds(false)->format('H:i'),
                        ]))->values()->all()),
                Section::make('Workspace categories offered')
                    ->description('Tick only what this property actually offers. The website shows a category at a property only when it is ticked here.')
                    ->schema([
                        CheckboxList::make('product_ids')
                            ->hiddenLabel()
                            ->options(fn (): array => Product::query()->orderBy('sort_order')->pluck('name', 'id')->all())
                            ->disabled(fn (): bool => ! auth()->user()?->can(Capability::ManageProducts))
                            ->helperText(fn (): ?string => auth()->user()?->can(Capability::ManageProducts) ? null : 'Only operations staff can change which categories a property offers.')
                            ->columns(2),
                    ]),
            ]);
    }

    /**
     * Fields a Property Manager cannot change on an existing property (SaveProperty refuses them too).
     */
    private static function isOperationsOnly(?Property $record): bool
    {
        return $record !== null && ! (auth()->user()?->hasCapabilityForEveryProperty(Capability::ManageProperties) ?? false);
    }
}
