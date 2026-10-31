<?php

namespace App\Filament\Clusters\Properties\Resources\Products\Pages;

use App\Actions\Properties\SetCategorySpaceTypes;
use App\Filament\Clusters\Properties\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;

    /**
     * The space types chosen on the form, saved after the category through the audited action.
     *
     * @var list<int|string>|null
     */
    protected ?array $spaceTypeIds = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        return Product::hasSpaceTypes() ? [...$data, 'space_type_ids' => $this->getRecord()->spaceTypes()->pluck('space_types.id')->all()] : $data;
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $this->spaceTypeIds = array_key_exists('space_type_ids', $data) ? array_values($data['space_type_ids'] ?? []) : null;
        unset($data['space_type_ids']);

        return $data;
    }

    protected function afterSave(): void
    {
        if ($this->spaceTypeIds !== null && Product::hasSpaceTypes()) {
            app(SetCategorySpaceTypes::class)->handle(auth()->user(), $this->getRecord(), $this->spaceTypeIds);
        }
    }
}
