<?php

namespace App\Filament\Clusters\Properties\Resources\Products;

use App\Filament\Clusters\Properties\PropertiesCluster;
use App\Filament\Clusters\Properties\Resources\Products\Pages\CreateProduct;
use App\Filament\Clusters\Properties\Resources\Products\Pages\EditProduct;
use App\Filament\Clusters\Properties\Resources\Products\Pages\ListProducts;
use App\Models\Product;
use BackedEnum;
use Filament\Actions\EditAction;
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
