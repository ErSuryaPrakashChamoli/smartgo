<?php

namespace App\Filament\Clusters\Properties\Resources\Spaces\Schemas;

use App\Actions\Inventory\AddSpaces;
use App\Enums\Inventory\InventoryMode;
use App\Models\Floor;
use App\Models\Offering;
use App\Models\Property;
use App\Models\SpaceType;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;

/**
 * The fields of a space, for adding one, adding a run of them, or changing one (SaveSpace, AddSpaces).
 * Only the modes the space type allows and only the categories that suit the chosen mode are offered
 * (BR-PRODCFG-2); the actions check both again.
 */
class SpaceForm
{
    public const string ONE = 'one';

    public const string MANY = 'many';

    public const string EDIT = 'edit';

    /**
     * @param  self::ONE|self::MANY|self::EDIT  $purpose
     * @return list<mixed>
     */
    public static function fields(Property $property, string $purpose, ?InventoryMode $currentMode = null): array
    {
        $seatsManagedSeparately = fn (Get $get): bool => $purpose === self::EDIT && $currentMode?->hasSeats() === true && InventoryMode::tryFrom((string) $get('inventory_mode')) === $currentMode;

        return [
            Select::make('floor_id')
                ->label('Floor')
                ->options(fn (): array => $property->floors()->get()->mapWithKeys(fn (Floor $floor): array => [$floor->getKey() => $floor->displayName()])->all())
                ->required()
                ->helperText('Floors are added on the property\'s page.'),
            Select::make('space_type_id')
                ->label('Space type')
                ->options(fn (): array => SpaceType::query()->orderBy('sort_order')->orderBy('name')->pluck('name', 'id')->all())
                ->required()
                ->live()
                ->afterStateUpdated(fn (Set $set, mixed $state) => $set('inventory_mode', SpaceType::query()->find($state)?->default_inventory_mode?->value)),
            Select::make('inventory_mode')
                ->label('Counted as')
                ->options(fn (Get $get): array => collect(InventoryMode::cases())
                    ->filter(fn (InventoryMode $mode): bool => SpaceType::query()->find($get('space_type_id'))?->allowsInventoryMode($mode) ?? true)
                    ->mapWithKeys(fn (InventoryMode $mode): array => [$mode->value => $mode->label()])->all())
                ->required()
                ->live()
                ->helperText(fn (Get $get): ?string => InventoryMode::tryFrom((string) $get('inventory_mode'))?->description()),
            ...($purpose === self::MANY ? [
                TextInput::make('count')->label('How many spaces')->integer()->required()->minValue(1)->maxValue(AddSpaces::MAX_SPACES),
                TextInput::make('code_prefix')->label('Code prefix')->required()->maxLength(24)->helperText('Each space gets the prefix and a number, for example A70-C gives A70-C01, A70-C02 and so on. Codes are permanent.'),
                TextInput::make('first_number')->label('First number')->integer()->required()->minValue(0)->default(1),
                TextInput::make('name_prefix')->label('Name prefix (optional)')->maxLength(70)->helperText('For example "Cabin" gives Cabin 1, Cabin 2 and so on.'),
            ] : []),
            ...($purpose === self::ONE ? [
                TextInput::make('code')->required()->maxLength(30)->helperText('Permanent, for example A70-C01. It can never be changed or reused.'),
            ] : []),
            ...($purpose !== self::MANY ? [
                TextInput::make('name')->label('Name (optional)')->maxLength(120),
            ] : []),
            TextInput::make('capacity_seats')
                ->label('Seats')
                ->integer()
                ->required(fn (Get $get): bool => ! $seatsManagedSeparately($get))
                ->minValue(1)
                ->maxValue(2000)
                ->hidden($seatsManagedSeparately)
                ->helperText(fn (Get $get): ?string => collect([
                    $purpose === self::MANY ? 'For each space.' : null,
                    InventoryMode::tryFrom((string) $get('inventory_mode'))?->hasSeats() ? 'One seat record is made for each seat.' : null,
                ])->filter()->implode(' ') ?: null),
            TextInput::make('area_sqft')->label('Area in square feet (optional)')->integer()->minValue(1)->maxValue(1000000),
            CheckboxList::make('offering_ids')
                ->label('Can be sold as')
                ->options(fn (Get $get): array => $property->offerings()->with('product')->where('is_active', true)->get()
                    ->filter(fn (Offering $offering): bool => ($mode = InventoryMode::tryFrom((string) $get('inventory_mode'))) !== null && $offering->product->allowsInventoryMode($mode))
                    ->mapWithKeys(fn (Offering $offering): array => [$offering->getKey() => $offering->product->name])->all())
                ->helperText('Only the categories this property offers that suit how the space is counted (BR-PRODCFG-2). One availability covers them all.'),
            Toggle::make('is_publicly_listable')
                ->label('May be shown to customers')
                ->inline(false)
                ->helperText('The website does not show spaces or availability yet; this records whether it may once it does.'),
            Textarea::make('reason')
                ->label('Reason for the change')
                ->required()
                ->maxLength(300)
                ->rows(2)
                ->helperText('Recorded in the audit log with the change (BR-INV-1).'),
        ];
    }
}
