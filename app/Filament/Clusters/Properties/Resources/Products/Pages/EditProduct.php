<?php

namespace App\Filament\Clusters\Properties\Resources\Products\Pages;

use App\Filament\Clusters\Properties\Resources\Products\ProductResource;
use Filament\Resources\Pages\EditRecord;

class EditProduct extends EditRecord
{
    protected static string $resource = ProductResource::class;
}
