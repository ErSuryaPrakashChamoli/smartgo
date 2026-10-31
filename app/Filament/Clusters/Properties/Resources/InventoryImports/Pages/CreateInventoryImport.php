<?php

namespace App\Filament\Clusters\Properties\Resources\InventoryImports\Pages;

use App\Actions\Inventory\UploadInventoryImport;
use App\Filament\Clusters\Properties\Resources\InventoryImports\InventoryImportResource;
use App\Models\User;
use Filament\Forms\Components\FileUpload;
use Filament\Resources\Pages\CreateRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

/**
 * Uploading a workbook checks it at once (UploadInventoryImport); the import's page then shows the
 * findings and the preview.
 */
class CreateInventoryImport extends CreateRecord
{
    protected static string $resource = InventoryImportResource::class;

    protected static bool $canCreateAnother = false;

    public function getTitle(): string
    {
        return 'Upload an inventory workbook';
    }

    public function form(Schema $schema): Schema
    {
        return $schema->columns(1)->components([
            Section::make()->schema([
                FileUpload::make('file')->label('Filled-in SG-INV-1.3 workbook (.xlsx, at most 10 MB)')->required()->storeFiles(false)->maxSize(10240)
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                    ->helperText('It is checked as soon as it is uploaded. Real client files are never committed to Git or used as test data.'),
            ]),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        /** @var User $user */
        $user = auth()->user();
        $file = $data['file'];

        if (! $file instanceof UploadedFile) {
            throw ValidationException::withMessages(['data.file' => 'Upload the workbook again.']);
        }

        try {
            return app(UploadInventoryImport::class)->handle($user, $file);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(['data.file' => collect($exception->errors())->flatten()->all()]);
        }
    }

    protected function getRedirectUrl(): string
    {
        return InventoryImportResource::getUrl('view', ['record' => $this->record]);
    }
}
