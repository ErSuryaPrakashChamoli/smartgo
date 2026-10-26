<?php

namespace App\Filament\Clusters\Website\Resources\MediaAssets\Pages;

use App\Actions\Media\CreateMediaAsset as CreateMediaAssetAction;
use App\Filament\Clusters\Website\Resources\MediaAssets\MediaAssetResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class CreateMediaAsset extends CreateRecord
{
    protected static string $resource = MediaAssetResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();
        $file = is_array($data['file']) ? Arr::first($data['file']) : $data['file'];

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages(['data.file' => 'Choose an image to upload.']);
        }

        try {
            return app(CreateMediaAssetAction::class)->handle($user, $file, Arr::except($data, ['file']));
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(Arr::mapWithKeys($exception->errors(), fn (array $messages, string $key): array => ["data.{$key}" => $messages]));
        }
    }

    protected function getRedirectUrl(): string
    {
        return static::getResource()::getUrl('index');
    }
}
