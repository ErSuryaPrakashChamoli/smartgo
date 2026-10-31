<?php

namespace App\Filament\Clusters\Properties\Resources\Products\Pages;

use App\Actions\Properties\SetCategorySpaceTypes;
use App\Filament\Clusters\Properties\Resources\Products\ProductResource;
use App\Models\Product;
use Filament\Resources\Pages\CreateRecord;

class CreateProduct extends CreateRecord
{
    protected static string $resource = ProductResource::class;

    protected static bool $canCreateAnother = false;

    /**
     * The space types chosen on the form, set after the category exists, through the audited action.
     *
     * @var list<int|string>|null
     */
    protected ?array $spaceTypeIds = null;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $this->spaceTypeIds = array_key_exists('space_type_ids', $data) ? array_values($data['space_type_ids'] ?? []) : null;
        unset($data['space_type_ids']);

        return $data;
    }

    protected function afterCreate(): void
    {
        if ($this->spaceTypeIds !== null && Product::hasSpaceTypes()) {
            app(SetCategorySpaceTypes::class)->handle(auth()->user(), $this->getRecord(), $this->spaceTypeIds);
        }
    }
}
