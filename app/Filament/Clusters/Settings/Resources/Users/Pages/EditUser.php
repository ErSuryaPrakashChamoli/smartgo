<?php

namespace App\Filament\Clusters\Settings\Resources\Users\Pages;

use App\Actions\Access\ResetTwoFactorAuthentication;
use App\Actions\Access\SyncUserRoles;
use App\Enums\Access\StaffRole;
use App\Filament\Clusters\Settings\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * @property User $record
 */
class EditUser extends EditRecord
{
    protected static string $resource = UserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('resetTwoFactorAuthentication')
                ->label('Reset two-step login')
                ->color('danger')
                ->requiresConfirmation()
                ->modalDescription('The user will have to set up two-step login again the next time they sign in.')
                ->authorize('update')
                ->hidden(fn (User $record): bool => $record->is(auth()->user()))
                ->action(function (User $record, ResetTwoFactorAuthentication $resetTwoFactorAuthentication): void {
                    $resetTwoFactorAuthentication->handle($record, auth()->user());

                    Notification::make()->success()->title('Two-step login reset')->send();
                }),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $data['roles'] = $this->record->getRoleNames()->all();

        return $data;
    }

    /**
     * @param  User  $record
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $roles = Arr::pull($data, 'roles', []);

        $record->update($data);

        try {
            app(SyncUserRoles::class)->handle($record, array_map(StaffRole::from(...), $roles), auth()->user());
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(
                Arr::mapWithKeys($exception->errors(), fn (array $messages, string $key): array => ["data.{$key}" => $messages]),
            );
        }

        return $record;
    }
}
