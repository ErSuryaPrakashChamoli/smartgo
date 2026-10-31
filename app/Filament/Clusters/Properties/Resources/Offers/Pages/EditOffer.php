<?php

namespace App\Filament\Clusters\Properties\Resources\Offers\Pages;

use App\Actions\Pricing\SaveOffer;
use App\Filament\Clusters\Properties\Resources\Offers\OfferResource;
use App\Models\Offer;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Changes a draft offer (SaveOffer).
 *
 * @property Offer $record
 */
class EditOffer extends EditRecord
{
    protected static string $resource = OfferResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return OfferResource::formData($this->record);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            return app(SaveOffer::class)->handle($user, $this->record, $data);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(Arr::mapWithKeys($exception->errors(), fn (array $messages, string $key): array => ['data.'.explode('.', $key)[0] => $messages]));
        }
    }

    protected function getRedirectUrl(): string
    {
        return OfferResource::getUrl('view', ['record' => $this->record]);
    }
}
