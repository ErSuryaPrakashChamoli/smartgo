<?php

namespace App\Filament\Clusters\Settings\Resources\Users\Schemas;

use App\Enums\Access\StaffRole;
use App\Models\User;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Password;

class UserForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Account')
                    ->schema([
                        TextInput::make('name')
                            ->required()
                            ->maxLength(255),
                        TextInput::make('email')
                            ->email()
                            ->required()
                            ->maxLength(255)
                            ->unique(),
                        TextInput::make('password')
                            ->password()
                            ->revealable()
                            ->rule(Password::default())
                            ->required(fn (string $operation): bool => $operation === 'create')
                            ->dehydrated(fn (?string $state): bool => filled($state))
                            ->helperText(fn (string $operation): ?string => $operation === 'edit' ? 'Leave blank to keep the current password.' : null),
                        Toggle::make('is_active')
                            ->label('Active')
                            ->default(true)
                            ->disabled(fn (?User $record): bool => $record?->is(auth()->user()) ?? false)
                            ->helperText('Inactive staff cannot sign in. You cannot deactivate your own account.'),
                    ]),
                Section::make('Access')
                    ->schema([
                        CheckboxList::make('roles')
                            ->label('Staff roles')
                            ->options(StaffRole::options())
                            ->required()
                            ->helperText('Staff need at least one role to enter the back office.'),
                    ]),
            ]);
    }
}
