<?php

namespace App\Filament\Clusters\Properties\Resources\Offers\Pages;

use App\Actions\Pricing\SaveOffer;
use App\Filament\Clusters\Properties\Resources\Offers\OfferResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class CreateOffer extends CreateRecord
{
    protected static string $resource = OfferResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            return app(SaveOffer::class)->handle($user, null, $data);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(Arr::mapWithKeys($exception->errors(), fn (array $messages, string $key): array => ['data.'.explode('.', $key)[0] => $messages]));
        }
    }

    protected function getRedirectUrl(): string
    {
        return OfferResource::getUrl('view', ['record' => $this->record]);
    }
}
