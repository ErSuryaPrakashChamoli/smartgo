<?php

namespace App\Filament\Clusters\Properties\Resources\Properties\Pages;

use App\Actions\Properties\SaveProperty;
use App\Filament\Clusters\Properties\Resources\Properties\PropertyResource;
use App\Filament\Clusters\Properties\Resources\PropertyProfiles\PropertyProfileResource;
use App\Models\Property;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * @property Property $record
 */
class EditProperty extends EditRecord
{
    protected static string $resource = PropertyResource::class;

    public function getSubheading(): ?string
    {
        return $this->record->isPubliclyListed() ? 'On the website at '.$this->record->path() : 'Not on the website: only open properties are listed.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('websiteProfile')
                ->label('Website profile')
                ->icon(Heroicon::OutlinedPhoto)
                ->color('gray')
                ->visible(fn (): bool => $this->record->profile !== null && (auth()->user()?->can('view', $this->record->profile) ?? false))
                ->url(fn (): ?string => PropertyProfileResource::urlFor($this->record)),
            Action::make('viewOnWebsite')
                ->label('View on the website')
                ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                ->color('gray')
                ->visible(fn (): bool => $this->record->isPubliclyListed())
                ->url(fn (): string => url((string) $this->record->path()), shouldOpenInNewTab: true),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $building = $this->record->primaryBuilding;

        return [
            ...$data,
            'operating_model' => $this->record->operating_model->value,
            'operational_status' => $this->record->operational_status->value,
            'building' => $building?->only(['code', 'name', 'address_line_1', 'address_line_2', 'locality', 'postal_code', 'state', 'latitude', 'longitude']) ?? [],
            'product_ids' => $this->record->products()->wherePivot('is_active', true)->pluck('products.id')->map(fn (int $id): string => (string) $id)->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();

        try {
            return app(SaveProperty::class)->handle($user, $this->record, $data);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(Arr::mapWithKeys($exception->errors(), fn (array $messages, string $key): array => ["data.{$key}" => $messages]));
        }
    }
}
