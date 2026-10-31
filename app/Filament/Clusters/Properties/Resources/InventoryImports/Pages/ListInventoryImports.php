<?php

namespace App\Filament\Clusters\Properties\Resources\InventoryImports\Pages;

use App\Filament\Clusters\Properties\Resources\InventoryImports\InventoryImportResource;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Filament\Support\Icons\Heroicon;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ListInventoryImports extends ListRecords
{
    /** The documented template (INVENTORY_IMPORT_SPEC §2). */
    public const string TEMPLATE = 'docs/templates/inventory-import/SmartGro_Inventory_Import_Template_SG-INV-1.3.xlsx';

    protected static string $resource = InventoryImportResource::class;

    public function getSubheading(): ?string
    {
        return 'Only real inventory supplied by SmartGro is imported. Nothing is written until someone other than the uploader approves it.';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('template')->label('Download the SG-INV-1.3 template')->icon(Heroicon::OutlinedArrowDownTray)->color('gray')
                ->action(fn (): BinaryFileResponse => response()->download(base_path(self::TEMPLATE))),
            CreateAction::make()->label('Upload workbook'),
        ];
    }
}
