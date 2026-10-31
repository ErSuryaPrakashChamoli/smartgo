<?php

namespace App\Filament\Clusters\Properties\Resources\Products;

use App\Enums\Inventory\InventoryMode;
use App\Filament\Clusters\Properties\PropertiesCluster;
use App\Filament\Clusters\Properties\Resources\Products\Pages\CreateProduct;
use App\Filament\Clusters\Properties\Resources\Products\Pages\EditProduct;
use App\Filament\Clusters\Properties\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use App\Models\Space;
use App\Models\SpaceType;
use BackedEnum;
use Closure;
use Filament\Actions\EditAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * The workspace categories (OD-07). Booking, pricing and cancellation rules (DATABASE_ERD §4.1) join
 * this screen with those features.
 */
class ProductResource extends Resource
{
    protected static ?string $model = Product::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedSquares2x2;

    protected static ?string $cluster = PropertiesCluster::class;

    protected static ?string $navigationLabel = 'Workspace categories';

    protected static ?string $modelLabel = 'workspace category';

    protected static ?string $slug = 'workspace-categories';

    protected static ?int $navigationSort = 5;

    protected static ?string $recordTitleAttribute = 'name';

    protected static bool $isGloballySearchable = false;

    public static function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make('Workspace category')->columns(2)->schema([
                TextInput::make('name')->required()->maxLength(80),
                TextInput::make('slug')->label('Website address')->required()->maxLength(80)
                    ->regex('/^[a-z0-9]+(?:-[a-z0-9]+)*$/')->unique(ignoreRecord: true),
                TextInput::make('key')->required()->maxLength(40)->regex('/^[a-z0-9_]+$/')->unique(ignoreRecord: true)
                    ->disabledOn('edit')->helperText('Permanent internal name, for example meeting_room.'),
                TextInput::make('sort_order')->label('Position')->integer()->minValue(0)->maxValue(9999)->default(0),
                Toggle::make('is_active')->label('Offered by SmartGro')->default(true)->inline(false)
                    ->helperText('Inactive categories disappear from the website.'),
                CheckboxList::make('allowed_inventory_modes')
                    ->label('Spaces it can be sold with')
                    ->options(InventoryMode::options())
                    ->columnSpanFull()
                    ->visible(fn (): bool => Product::hasInventoryModes())
                    ->rule(fn (?Product $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        $inUse = $record === null ? collect() : Space::query()
                            ->whereHas('offerings', fn ($offering) => $offering->where('product_id', $record->getKey()))
                            ->whereNotIn('inventory_mode', is_array($value) ? $value : [])
                            ->pluck('code');

                        if ($inUse->isNotEmpty()) {
                            $fail('These spaces are sold as this category and counted in a way no longer ticked; change them first: '.$inUse->implode(', ').'.');
                        }
                    })
                    ->helperText('Which spaces can be sold as this category (BR-PRODCFG-2). None ticked: it uses no physical space, like a virtual office (BR-PRODCFG-3).'),
                CheckboxList::make('space_type_ids')
                    ->label('Space types it can be sold with')
                    ->options(fn (): array => SpaceType::query()->orderBy('sort_order')->orderBy('id')->pluck('name', 'id')->all())
                    ->columns(2)
                    ->columnSpanFull()
                    ->visible(fn (): bool => Product::hasSpaceTypes())
                    ->rule(fn (?Product $record): Closure => function (string $attribute, mixed $value, Closure $fail) use ($record): void {
                        $inUse = $record === null ? collect() : Space::query()
                            ->whereHas('offerings', fn ($offering) => $offering->where('product_id', $record->getKey()))
                            ->whereNotIn('space_type_id', is_array($value) ? $value : [])
                            ->orderBy('code')
                            ->pluck('code');

                        if ($inUse->isNotEmpty()) {
                            $fail('These spaces are sold as this category and are of a type no longer ticked; change them first: '.$inUse->implode(', ').'.');
                        }
                    })
                    ->helperText('A space is sold as this category only if its type is ticked here and the way it is counted is ticked above (BR-PRODCFG-2). None ticked: no space can be sold as it.'),
            ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sort_order')
            ->columns([
                TextColumn::make('name')->weight('semibold')->description(fn (Product $record): string => $record->key),
                TextColumn::make('properties_count')->counts('properties')->label('Offered at')->suffix(' properties'),
                IconColumn::make('is_active')->label('Offered')->boolean(),
                TextColumn::make('sort_order')->label('Position')->sortable(),
            ])
            ->recordActions([EditAction::make()->label('Open')]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProducts::route('/'),
            'create' => CreateProduct::route('/create'),
            'edit' => EditProduct::route('/{record}/edit'),
        ];
    }
}
