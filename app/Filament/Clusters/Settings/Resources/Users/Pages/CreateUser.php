<?php

namespace App\Filament\Clusters\Settings\Resources\Users\Pages;

use App\Actions\Access\SyncUserRoles;
use App\Enums\Access\StaffRole;
use App\Filament\Clusters\Settings\Resources\Users\UserResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;

class CreateUser extends CreateRecord
{
    protected static string $resource = UserResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        $roles = Arr::pull($data, 'roles', []);

        $user = User::create($data);

        app(SyncUserRoles::class)->handle($user, array_map(StaffRole::from(...), $roles), auth()->user());

        return $user;
    }
}
