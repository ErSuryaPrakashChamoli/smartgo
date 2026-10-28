<?php

namespace App\Filament\Clusters\Properties\Resources\Properties\Pages;

use App\Actions\Properties\SaveProperty;
use App\Filament\Clusters\Properties\Resources\Properties\PropertyResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class CreateProperty extends CreateRecord
{
    protected static string $resource = PropertyResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            return app(SaveProperty::class)->handle($user, null, $data);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(Arr::mapWithKeys($exception->errors(), fn (array $messages, string $key): array => ["data.{$key}" => $messages]));
        }
    }
}
