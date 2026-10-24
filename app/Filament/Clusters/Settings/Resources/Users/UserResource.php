<?php

namespace App\Filament\Clusters\Settings\Resources\Users;

use App\Filament\Clusters\Settings\Resources\Users\Pages\CreateUser;
use App\Filament\Clusters\Settings\Resources\Users\Pages\EditUser;
use App\Filament\Clusters\Settings\Resources\Users\Pages\ListUsers;
use App\Filament\Clusters\Settings\Resources\Users\Schemas\UserForm;
use App\Filament\Clusters\Settings\Resources\Users\Tables\UsersTable;
use App\Filament\Clusters\Settings\SettingsCluster;
use App\Models\User;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $cluster = SettingsCluster::class;

    protected static ?string $navigationLabel = 'Staff users';

    protected static ?string $modelLabel = 'staff user';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return UserForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return UsersTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListUsers::route('/'),
            'create' => CreateUser::route('/create'),
            'edit' => EditUser::route('/{record}/edit'),
        ];
    }
}
