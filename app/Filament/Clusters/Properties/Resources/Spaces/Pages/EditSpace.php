<?php

namespace App\Filament\Clusters\Properties\Resources\Spaces\Pages;

use App\Actions\Inventory\SaveSpace;
use App\Filament\Clusters\Properties\Resources\Spaces\Schemas\SpaceForm;
use App\Filament\Clusters\Properties\Resources\Spaces\SpaceResource;
use App\Models\Space;
use App\Models\User;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Validation\ValidationException;

/**
 * Changes a space through SaveSpace, which locks it and refuses a change of counting or a lower
 * capacity that allocations no longer fit (BR-INV-3, BR-INV-4). A zone's seats are changed on its page.
 *
 * @property Space $record
 */
class EditSpace extends EditRecord
{
    protected static string $resource = SpaceResource::class;

    public function getTitle(): string
    {
        return 'Edit space '.$this->record->code;
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()->columns(2)->schema(SpaceForm::fields($this->record->property, SpaceForm::EDIT, $this->record->inventory_mode)),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return [
            ...Arr::only($data, ['floor_id', 'space_type_id', 'name', 'capacity_seats', 'area_sqft', 'is_publicly_listable']),
            'inventory_mode' => $this->record->inventory_mode->value,
            'offering_ids' => $this->record->offerings()->pluck('offerings.id')->map(fn (int $id): string => (string) $id)->all(),
            'reason' => null,
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
            return app(SaveSpace::class)->handle($user, $this->record->property, $this->record, [...$data, 'capacity_seats' => $data['capacity_seats'] ?? $this->record->capacity_seats]);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(Arr::mapWithKeys($exception->errors(), fn (array $messages, string $key): array => ['data.'.(str_starts_with($key, 'offering_ids') ? 'offering_ids' : $key) => $messages]));
        }
    }

    protected function getRedirectUrl(): string
    {
        return SpaceResource::getUrl('view', ['record' => $this->record]);
    }
}
