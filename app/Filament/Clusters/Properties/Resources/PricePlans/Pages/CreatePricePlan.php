<?php

namespace App\Filament\Clusters\Properties\Resources\PricePlans\Pages;

use App\Actions\Pricing\SavePricePlan;
use App\Filament\Clusters\Properties\Resources\PricePlans\PricePlanResource;
use App\Models\User;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

class CreatePricePlan extends CreateRecord
{
    protected static string $resource = PricePlanResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            return app(SavePricePlan::class)->handle($user, null, $data);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(Arr::mapWithKeys($exception->errors(), fn (array $messages, string $key): array => ["data.{$key}" => $messages]));
        }
    }

    protected function getRedirectUrl(): string
    {
        return PricePlanResource::getUrl('view', ['record' => $this->record]);
    }
}
