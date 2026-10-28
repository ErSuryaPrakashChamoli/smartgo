<?php

namespace App\Filament\Clusters\Website\Resources\TwinModels\Pages;

use App\Actions\Twin\CreateTwinModel as CreateTwinModelAction;
use App\Filament\Clusters\Website\Resources\TwinModels\TwinModelResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class CreateTwinModel extends CreateRecord
{
    protected static string $resource = TwinModelResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * Every reason a file fails the checks is shown together under the file field.
     *
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();
        $file = is_array($data['file']) ? Arr::first($data['file']) : $data['file'];

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages(['data.file' => 'Choose a .glb file to upload.']);
        }

        try {
            return app(CreateTwinModelAction::class)->handle($user, $file, Arr::except($data, ['file']));
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(Arr::mapWithKeys($exception->errors(), fn (array $messages, string $key): array => ["data.{$key}" => implode(' ', $messages)]));
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('view', ['record' => $this->getRecord()]);
    }
}
